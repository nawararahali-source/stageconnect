"""Compare, évalue et sauvegarde des modèles de régression pour le matching."""

from pathlib import Path

import joblib
import matplotlib

matplotlib.use("Agg")

import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression, Ridge
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import cross_validate, train_test_split
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler

from generate_dataset import FEATURES


RANDOM_STATE = 42
TEST_SIZE = 0.2
CV_FOLDS = 5


def create_models() -> dict[str, object]:
    """Crée les quatre régressions à comparer."""
    return {
        "LinearRegression": make_pipeline(StandardScaler(), LinearRegression()),
        "Ridge": make_pipeline(StandardScaler(), Ridge(alpha=1.0)),
        "RandomForestRegressor": RandomForestRegressor(
            n_estimators=250,
            min_samples_leaf=2,
            random_state=RANDOM_STATE,
            n_jobs=1,
        ),
        "GradientBoostingRegressor": GradientBoostingRegressor(
            n_estimators=150,
            learning_rate=0.05,
            max_depth=2,
            loss="huber",
            random_state=RANDOM_STATE,
        ),
    }


def load_dataset(path: Path) -> tuple[pd.DataFrame, pd.Series]:
    """Vérifie le contrat des colonnes et la validité des scores."""
    if not path.exists():
        raise FileNotFoundError(
            f"Dataset introuvable : {path}. Lancez d'abord generate_dataset.py."
        )

    dataset = pd.read_csv(path)
    required_columns = FEATURES + ["score"]
    missing_columns = [column for column in required_columns if column not in dataset]
    if missing_columns:
        raise ValueError(f"Colonnes manquantes dans le dataset : {missing_columns}")
    if len(dataset) < CV_FOLDS:
        raise ValueError(f"Il faut au moins {CV_FOLDS} lignes pour la validation croisée.")

    dataset = dataset[required_columns].dropna()
    if dataset.empty:
        raise ValueError("Le dataset ne contient aucune ligne exploitable.")

    features = dataset[FEATURES].astype(float)
    target = dataset["score"].astype(float)
    if not np.isfinite(features.to_numpy()).all() or not np.isfinite(target.to_numpy()).all():
        raise ValueError("Le dataset contient des valeurs non numériques ou infinies.")
    if not target.between(0, 100).all():
        raise ValueError("Les scores doivent être compris entre 0 et 100.")
    return features, target


def evaluate_models(
    features: pd.DataFrame, target: pd.Series
) -> tuple[pd.DataFrame, object, str]:
    """Évalue chaque modèle en validation croisée puis sur le test indépendant."""
    x_train, x_test, y_train, y_test = train_test_split(
        features,
        target,
        test_size=TEST_SIZE,
        random_state=RANDOM_STATE,
    )
    results = []
    fitted_models = {}

    scoring = {
        "mae": "neg_mean_absolute_error",
        "rmse": "neg_root_mean_squared_error",
        "r2": "r2",
    }

    for name, model in create_models().items():
        cross_validation = cross_validate(
            model,
            x_train,
            y_train,
            cv=CV_FOLDS,
            scoring=scoring,
            n_jobs=1,
        )
        model.fit(x_train, y_train)
        predictions = model.predict(x_test)
        fitted_models[name] = model
        results.append(
            {
                "Modèle": name,
                "MAE test": mean_absolute_error(y_test, predictions),
                "RMSE test": np.sqrt(mean_squared_error(y_test, predictions)),
                "R² test": r2_score(y_test, predictions),
                "MAE CV": -cross_validation["test_mae"].mean(),
                "RMSE CV": -cross_validation["test_rmse"].mean(),
                "R² CV": cross_validation["test_r2"].mean(),
            }
        )

    comparison = pd.DataFrame(results).sort_values("RMSE CV").reset_index(drop=True)
    best_name = str(comparison.iloc[0]["Modèle"])
    return comparison, fitted_models[best_name], best_name


def save_comparison_plot(comparison: pd.DataFrame, output_path: Path) -> None:
    """Trace les trois métriques sans mélanger leurs échelles."""
    figure, axes = plt.subplots(1, 3, figsize=(15, 5))
    metrics = [
        ("MAE test", "MAE — plus bas est meilleur"),
        ("RMSE test", "RMSE — plus bas est meilleur"),
        ("R² test", "R² — plus haut est meilleur"),
    ]
    colors = ["#4f46e5", "#0ea5e9", "#10b981", "#f59e0b"]

    for axis, (metric, title) in zip(axes, metrics):
        axis.bar(comparison["Modèle"], comparison[metric], color=colors)
        axis.set_title(title)
        axis.tick_params(axis="x", labelrotation=25)
        axis.grid(axis="y", alpha=0.25)
        axis.set_axisbelow(True)

    figure.suptitle("Comparaison des modèles de matching")
    figure.tight_layout()
    output_path.parent.mkdir(parents=True, exist_ok=True)
    figure.savefig(output_path, dpi=160, bbox_inches="tight")
    plt.close(figure)


def train(data_path: Path, output_dir: Path) -> pd.DataFrame:
    """Entraîne, compare et exporte le meilleur estimateur."""
    features, target = load_dataset(data_path)
    comparison, best_model, best_name = evaluate_models(features, target)

    output_dir.mkdir(parents=True, exist_ok=True)
    model_path = output_dir / "model.joblib"
    chart_path = output_dir / "comparison.png"
    best_model.fit(features, target)
    joblib.dump(best_model, model_path)
    save_comparison_plot(comparison, chart_path)

    print(f"Évaluation sur un test indépendant ({TEST_SIZE:.0%}) et CV à {CV_FOLDS} folds")
    print(comparison.to_string(index=False, float_format=lambda value: f"{value:.3f}"))
    print(f"\nMeilleur modèle (RMSE CV) : {best_name}")
    print(f"Modèle sauvegardé : {model_path}")
    print(f"Graphique sauvegardé : {chart_path}")
    return comparison


if __name__ == "__main__":
    service_dir = Path(__file__).resolve().parent
    train(service_dir / "dataset.csv", service_dir)
