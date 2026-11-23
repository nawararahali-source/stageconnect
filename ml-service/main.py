"""API HTTP pour servir le modèle de compatibilité candidat-offre."""

import logging
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Annotated, AsyncIterator

import joblib
import numpy as np
import pandas as pd
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, ConfigDict, Field
from sklearn.base import clone
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split

from generate_dataset import FEATURES
from train_model import RANDOM_STATE, TEST_SIZE, load_dataset


LOGGER = logging.getLogger("ml_service")
SERVICE_DIR = Path(__file__).resolve().parent
MODEL_PATH = SERVICE_DIR / "model.joblib"
DATASET_PATH = SERVICE_DIR / "dataset.csv"

REFERENCE_FEATURE_VALUES = {
    "skills_match_ratio": 0.5,
    "nb_skills_matched": 1.0,
    "avg_skill_level": 2.0,
    "domain_match": 0.0,
    "nb_projects": 1.0,
    "nb_experiences": 1.0,
    "study_level": 3.0,
    "location_match": 0.0,
    "tfidf_similarity": 0.5,
}

ml_model = None
training_metrics: dict[str, float] | None = None
feature_reference_values = REFERENCE_FEATURE_VALUES.copy()
model_load_error: str | None = None
metrics_load_error: str | None = None


class PredictionRequest(BaseModel):
    """Caractéristiques validées d'une paire candidat-offre."""

    model_config = ConfigDict(extra="forbid")

    skills_match_ratio: Annotated[float, Field(ge=0, le=1)]
    nb_skills_matched: Annotated[int, Field(ge=0, le=100)]
    avg_skill_level: Annotated[float, Field(ge=0, le=3)]
    domain_match: Annotated[int, Field(ge=0, le=1)]
    nb_projects: Annotated[int, Field(ge=0, le=100)]
    nb_experiences: Annotated[int, Field(ge=0, le=100)]
    study_level: Annotated[int, Field(ge=1, le=5)]
    location_match: Annotated[int, Field(ge=0, le=2)]
    tfidf_similarity: Annotated[float, Field(ge=0, le=1)]

    def to_features(self) -> pd.DataFrame:
        """Conserve exactement l'ordre des caractéristiques utilisé à l'entraînement."""
        return pd.DataFrame(
            [[getattr(self, feature) for feature in FEATURES]],
            columns=FEATURES,
        )


class FeatureContribution(BaseModel):
    """Importance globale du champ et variation locale estimée du score."""

    valeur: float
    poids_pourcent: float
    contribution: float


class PredictionResponse(BaseModel):
    score: float
    niveau: str
    contributions: dict[str, FeatureContribution]
    source: str


class HealthResponse(BaseModel):
    status: str
    model_loaded: bool
    fallback_available: bool
    message: str


class MetricsResponse(BaseModel):
    mae: float
    rmse: float
    r2: float
    model: str
    test_size: float


def _compute_holdout_metrics(model: object) -> tuple[dict[str, float], pd.DataFrame]:
    """Évalue une copie du modèle sur le même split de test indépendant."""
    features, target = load_dataset(DATASET_PATH)
    train_features, test_features, train_target, test_target = train_test_split(
        features,
        target,
        test_size=TEST_SIZE,
        random_state=RANDOM_STATE,
    )
    evaluation_model = clone(model)
    evaluation_model.fit(train_features, train_target)
    predictions = evaluation_model.predict(test_features)

    return (
        {
            "mae": float(mean_absolute_error(test_target, predictions)),
            "rmse": float(np.sqrt(mean_squared_error(test_target, predictions))),
            "r2": float(r2_score(test_target, predictions)),
        },
        features,
    )


def initialize_service() -> None:
    """Charge le modèle et les métriques; le fallback reste actif en cas d'échec."""
    global ml_model, training_metrics, feature_reference_values
    global model_load_error, metrics_load_error

    ml_model = None
    training_metrics = None
    feature_reference_values = REFERENCE_FEATURE_VALUES.copy()
    model_load_error = None
    metrics_load_error = None

    try:
        if not MODEL_PATH.is_file():
            raise FileNotFoundError(
                f"Modèle absent : {MODEL_PATH}. Exécutez d'abord train_model.py."
            )
        loaded_model = joblib.load(MODEL_PATH)
        model_features = getattr(loaded_model, "feature_names_in_", None)
        if model_features is not None and list(model_features) != FEATURES:
            raise ValueError("L'ordre des caractéristiques du modèle ne correspond pas à l'API.")
        ml_model = loaded_model
    except Exception as error:
        model_load_error = str(error)
        LOGGER.exception("Chargement du modèle ML impossible; le fallback sera utilisé.")

    if ml_model is None:
        metrics_load_error = "Les métriques ML sont indisponibles sans modèle chargé."
        return

    try:
        training_metrics, features = _compute_holdout_metrics(ml_model)
        feature_reference_values = {
            feature: float(features[feature].median()) for feature in FEATURES
        }
    except Exception as error:
        metrics_load_error = str(error)
        LOGGER.exception(
            "Calcul des métriques impossible; les prédictions ML restent disponibles."
        )


@asynccontextmanager
async def lifespan(_: FastAPI) -> AsyncIterator[None]:
    initialize_service()
    yield


app = FastAPI(
    title="StageConnect AI - Matching ML",
    description="API de régression pour estimer la compatibilité candidat-offre.",
    version="1.0.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:4200",
        "http://127.0.0.1:4200",
        "http://localhost:4201",
        "http://127.0.0.1:4201",
    ],
    allow_credentials=True,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["Authorization", "Content-Type"],
)


def _fallback_prediction(features: pd.DataFrame) -> tuple[float, dict[str, float]]:
    """Applique les poids 50/20/15/10/5 si le modèle ML ne répond pas."""
    row = features.iloc[0]
    skill_factor = (
        0.25 + 0.25 * row["avg_skill_level"]
        if row["nb_skills_matched"] > 0
        else 0
    )
    portfolio_coverage = min(
        (row["nb_projects"] + row["nb_experiences"]) / 4,
        1,
    )
    contributions = {
        "skills_match_ratio": 50 * row["skills_match_ratio"] * skill_factor,
        "nb_skills_matched": 0.0,
        "avg_skill_level": 0.0,
        "domain_match": 20 * row["domain_match"],
        "nb_projects": 15 * 0.65 * portfolio_coverage,
        "nb_experiences": 0.0,
        "study_level": 10 * row["study_level"] / 5,
        "location_match": 5 * int(row["location_match"] > 0),
        "tfidf_similarity": 15 * 0.35 * row["tfidf_similarity"],
    }
    score = float(np.clip(sum(contributions.values()), 0, 100))
    return score, contributions


def _feature_importance_weights(model: object) -> dict[str, float]:
    """Retourne des poids globaux normalisés à 100 %."""
    importances = getattr(model, "feature_importances_", None)
    if importances is None and hasattr(model, "named_steps"):
        estimator = model.named_steps.get("linearregression") or model.named_steps.get("ridge")
        importances = getattr(estimator, "coef_", None)

    if importances is None:
        equal_weight = 100 / len(FEATURES)
        return {feature: equal_weight for feature in FEATURES}

    values = np.asarray(importances, dtype=float).reshape(-1)
    if len(values) != len(FEATURES):
        raise ValueError("Le modèle expose un nombre inattendu de poids de caractéristiques.")

    values = np.abs(values)
    total = float(values.sum())
    if total == 0:
        equal_weight = 100 / len(FEATURES)
        return {feature: equal_weight for feature in FEATURES}

    return {
        feature: float(weight / total * 100)
        for feature, weight in zip(FEATURES, values)
    }


def _build_contributions(
    model: object,
    features: pd.DataFrame,
    prediction: float,
    fallback_contributions: dict[str, float] | None = None,
) -> dict[str, FeatureContribution]:
    """Associe l'importance globale à l'effet local de chaque caractéristique."""
    if fallback_contributions is not None:
        total_weight = sum(fallback_contributions.values())
        weights = {
            feature: (
                fallback_contributions[feature] / total_weight * 100
                if total_weight
                else 0.0
            )
            for feature in FEATURES
        }
        local_effects = fallback_contributions
    else:
        weights = _feature_importance_weights(model)
        local_effects = {}
        for feature in FEATURES:
            reference_row = features.copy()
            reference_row.loc[:, feature] = feature_reference_values[feature]
            local_effects[feature] = float(prediction - model.predict(reference_row)[0])

    return {
        feature: FeatureContribution(
            valeur=float(features.iloc[0][feature]),
            poids_pourcent=round(weights[feature], 2),
            contribution=round(float(local_effects[feature]), 2),
        )
        for feature in FEATURES
    }


def _score_level(score: float) -> str:
    if score < 40:
        return "Faible"
    if score < 70:
        return "Moyen"
    return "Élevé"


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    """Indique si le modèle est chargé et si le fallback est disponible."""
    if ml_model is not None:
        return HealthResponse(
            status="healthy",
            model_loaded=True,
            fallback_available=True,
            message="Le modèle ML est prêt.",
        )

    return HealthResponse(
        status="degraded",
        model_loaded=False,
        fallback_available=True,
        message="Le modèle ML est indisponible; le scoring pondéré sera utilisé.",
    )


@app.get("/metrics", response_model=MetricsResponse)
def metrics() -> MetricsResponse:
    """Retourne MAE, RMSE et R² sur le split de test indépendant."""
    if ml_model is None:
        raise HTTPException(
            status_code=503,
            detail=f"Métriques indisponibles : le modèle ML n'est pas chargé. {model_load_error or ''}".strip(),
        )
    if training_metrics is None:
        raise HTTPException(
            status_code=503,
            detail=f"Métriques indisponibles : {metrics_load_error or 'évaluation non réalisée.'}",
        )

    return MetricsResponse(
        **training_metrics,
        model=type(ml_model).__name__,
        test_size=TEST_SIZE,
    )


@app.post("/predict", response_model=PredictionResponse)
def predict(request: PredictionRequest) -> PredictionResponse:
    """Prédit un score ML, ou utilise le scoring pondéré si le modèle échoue."""
    features = request.to_features()

    if ml_model is None:
        score, fallback_contributions = _fallback_prediction(features)
        contributions = _build_contributions(
            None,
            features,
            score,
            fallback_contributions,
        )
        source = "fallback"
    else:
        try:
            raw_score = float(ml_model.predict(features)[0])
            if not np.isfinite(raw_score):
                raise ValueError("Le modèle a retourné un score non fini.")
            score = float(np.clip(raw_score, 0, 100))
            contributions = _build_contributions(ml_model, features, score)
            source = "ml"
        except Exception:
            LOGGER.exception("Prédiction ML impossible; utilisation du scoring pondéré.")
            score, fallback_contributions = _fallback_prediction(features)
            contributions = _build_contributions(
                None,
                features,
                score,
                fallback_contributions,
            )
            source = "fallback"

    return PredictionResponse(
        score=round(score, 2),
        niveau=_score_level(score),
        contributions=contributions,
        source=source,
    )
