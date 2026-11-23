"""Tests du service ML de StageConnect (TC-12 : score, niveaux, repli).

Lancer depuis le dossier ml-service :  pytest -v
"""
import numpy as np
import pandas as pd
import pytest
from fastapi.testclient import TestClient

import main

VALIDE = {
    "skills_match_ratio": 0.6,
    "nb_skills_matched": 3,
    "avg_skill_level": 2.0,
    "domain_match": 1,
    "nb_projects": 2,
    "nb_experiences": 1,
    "study_level": 3,
    "location_match": 1,
    "tfidf_similarity": 0.5,
}

MAXIMUM = {
    "skills_match_ratio": 1.0,
    "nb_skills_matched": 3,
    "avg_skill_level": 3.0,
    "domain_match": 1,
    "nb_projects": 2,
    "nb_experiences": 2,
    "study_level": 5,
    "location_match": 2,
    "tfidf_similarity": 1.0,
}

MINIMUM = {
    "skills_match_ratio": 0.0,
    "nb_skills_matched": 0,
    "avg_skill_level": 0.0,
    "domain_match": 0,
    "nb_projects": 0,
    "nb_experiences": 0,
    "study_level": 1,
    "location_match": 0,
    "tfidf_similarity": 0.0,
}


def en_dataframe(valeurs: dict) -> pd.DataFrame:
    return pd.DataFrame([[valeurs[f] for f in main.FEATURES]], columns=main.FEATURES)


@pytest.fixture()
def client():
    with TestClient(main.app) as c:
        yield c


# ---------------- niveaux de score (valeurs limites) ----------------
@pytest.mark.parametrize(
    "score, niveau",
    [
        (0, "Faible"),
        (39.99, "Faible"),
        (40, "Moyen"),
        (69.99, "Moyen"),
        (70, "Élevé"),
        (100, "Élevé"),
    ],
)
def test_niveau_selon_le_score(score, niveau):
    assert main._score_level(score) == niveau


# ---------------- score de repli (poids 50/20/15/10/5) ----------------
def test_repli_score_maximal_vaut_100():
    score, _ = main._fallback_prediction(en_dataframe(MAXIMUM))
    assert score == pytest.approx(100.0)


def test_repli_score_minimal_vaut_2():
    # Seul le niveau d'etudes (minimum 1) rapporte 10 * 1 / 5 = 2 points.
    score, _ = main._fallback_prediction(en_dataframe(MINIMUM))
    assert score == pytest.approx(2.0)


def test_repli_poids_domaine_et_localisation():
    _, contributions = main._fallback_prediction(en_dataframe(MAXIMUM))
    assert contributions["domain_match"] == pytest.approx(20.0)
    assert contributions["location_match"] == pytest.approx(5.0)
    assert contributions["study_level"] == pytest.approx(10.0)


@pytest.mark.parametrize("valeurs", [MINIMUM, VALIDE, MAXIMUM])
def test_repli_score_toujours_entre_0_et_100(valeurs):
    score, _ = main._fallback_prediction(en_dataframe(valeurs))
    assert 0 <= score <= 100


# ---------------- API ----------------
def test_health_indique_que_le_repli_est_disponible(client):
    reponse = client.get("/health")
    assert reponse.status_code == 200
    corps = reponse.json()
    assert corps["status"] in ("healthy", "degraded")
    assert corps["fallback_available"] is True


def test_predict_valide_renvoie_un_score_borne(client):
    reponse = client.post("/predict", json=VALIDE)
    assert reponse.status_code == 200
    corps = reponse.json()
    assert 0 <= corps["score"] <= 100
    assert corps["niveau"] in ("Faible", "Moyen", "Élevé")
    assert corps["source"] in ("ml", "fallback")
    assert set(corps["contributions"]) == set(main.FEATURES)


@pytest.mark.parametrize(
    "champ, valeur",
    [
        ("skills_match_ratio", 1.5),
        ("avg_skill_level", 3.5),
        ("domain_match", 2),
        ("study_level", 0),
        ("study_level", 6),
        ("location_match", 3),
    ],
)
def test_predict_refuse_les_valeurs_hors_bornes(client, champ, valeur):
    donnees = dict(VALIDE, **{champ: valeur})
    assert client.post("/predict", json=donnees).status_code == 422


def test_predict_refuse_un_champ_inconnu(client):
    assert client.post("/predict", json=dict(VALIDE, bonus=1)).status_code == 422


def test_predict_refuse_un_champ_manquant(client):
    donnees = dict(VALIDE)
    del donnees["study_level"]
    assert client.post("/predict", json=donnees).status_code == 422


# ---------------- repli et robustesse du modele ----------------
def test_repli_quand_le_modele_est_absent(client, monkeypatch):
    monkeypatch.setattr(main, "ml_model", None)
    corps = client.post("/predict", json=VALIDE).json()
    assert corps["source"] == "fallback"
    assert 0 <= corps["score"] <= 100


class ModeleEnPanne:
    def predict(self, _):
        raise RuntimeError("panne du modele")


class ModeleNaN:
    def predict(self, _):
        return np.array([float("nan")])


class ModeleTropHaut:
    def predict(self, _):
        return np.array([150.0])


class ModeleNegatif:
    def predict(self, _):
        return np.array([-20.0])


@pytest.mark.parametrize("modele", [ModeleEnPanne(), ModeleNaN()])
def test_repli_quand_le_modele_echoue(client, monkeypatch, modele):
    monkeypatch.setattr(main, "ml_model", modele)
    corps = client.post("/predict", json=VALIDE).json()
    assert corps["source"] == "fallback"


def test_score_ml_trop_haut_est_ramene_a_100(client, monkeypatch):
    monkeypatch.setattr(main, "ml_model", ModeleTropHaut())
    corps = client.post("/predict", json=VALIDE).json()
    assert corps["score"] == 100
    assert corps["niveau"] == "Élevé"


def test_score_ml_negatif_est_ramene_a_0(client, monkeypatch):
    monkeypatch.setattr(main, "ml_model", ModeleNegatif())
    corps = client.post("/predict", json=VALIDE).json()
    assert corps["score"] == 0
    assert corps["niveau"] == "Faible"
