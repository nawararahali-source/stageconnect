"""Génère un jeu synthétique de paires candidat-offre pour le prototype ML."""

from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer


RANDOM_STATE = 42
ROW_COUNT = 2000
FEATURES = [
    "skills_match_ratio",
    "nb_skills_matched",
    "avg_skill_level",
    "domain_match",
    "nb_projects",
    "nb_experiences",
    "study_level",
    "location_match",
    "tfidf_similarity",
]

DOMAIN_SKILLS = {
    "comptabilite": [
        "comptabilite",
        "excel",
        "gestion",
        "fiscalite",
        "audit",
        "facturation",
        "tva",
        "bilan",
    ],
    "informatique": [
        "python",
        "php",
        "sql",
        "laravel",
        "angular",
        "reseaux",
        "linux",
        "cybersecurite",
    ],
    "marketing": [
        "marketing",
        "communication",
        "seo",
        "reseaux sociaux",
        "publicite",
        "redaction",
        "analyse marche",
        "canva",
    ],
    "ressources humaines": [
        "recrutement",
        "gestion rh",
        "paie",
        "communication",
        "droit social",
        "formation",
        "organisation",
        "excel",
    ],
    "logistique": [
        "logistique",
        "stock",
        "approvisionnement",
        "excel",
        "transport",
        "planification",
        "gestion",
        "analyse donnees",
    ],
}

DOMAIN_CONTEXT = {
    "comptabilite": "comptabilite comptable bilan audit fiscalite tva budget entreprise",
    "informatique": "informatique logiciel developpement reseau application donnees numerique",
    "marketing": "marketing communication marque publicite contenu clientele marche",
    "ressources humaines": "ressources humaines recrutement equipe formation personnel paie",
    "logistique": "logistique transport stock approvisionnement livraison planification",
}

STUDY_LEVELS = np.array([1, 2, 3, 4, 5], dtype=np.int64)
STUDY_LEVEL_NAMES = {
    1: "L1",
    2: "L2",
    3: "L3",
    4: "M1",
    5: "M2",
}


def build_text_pairs(
    candidate_texts: list[str], offer_texts: list[str]
) -> np.ndarray:
    """Calcule une similarité TF-IDF pour chaque paire, sans croiser les paires."""
    if not candidate_texts:
        return np.array([], dtype=float)

    vectorizer = TfidfVectorizer(ngram_range=(1, 2), min_df=1)
    combined_matrix = vectorizer.fit_transform(candidate_texts + offer_texts)
    candidate_matrix = combined_matrix[: len(candidate_texts)]
    offer_matrix = combined_matrix[len(candidate_texts) :]
    return np.asarray(candidate_matrix.multiply(offer_matrix).sum(axis=1)).ravel()


def create_candidate_offer_pair(
    rng: np.random.Generator,
) -> tuple[dict[str, float | int], str, str]:
    """Construit les caractéristiques et les textes d'une paire synthétique."""
    offer_domain = str(rng.choice(list(DOMAIN_SKILLS)))
    domain_match = int(rng.random() < 0.62)
    candidate_domain = offer_domain if domain_match else str(
        rng.choice([domain for domain in DOMAIN_SKILLS if domain != offer_domain])
    )

    required_skills = rng.choice(
        DOMAIN_SKILLS[offer_domain],
        size=int(rng.integers(3, 7)),
        replace=False,
    )
    match_probability = 0.78 if domain_match else 0.18
    matched_count = int(rng.binomial(len(required_skills), match_probability))
    matched_skills = (
        rng.choice(required_skills, size=matched_count, replace=False).tolist()
        if matched_count
        else []
    )

    if matched_count:
        matched_levels = rng.choice(
            [1, 2, 3],
            size=matched_count,
            p=[0.25, 0.5, 0.25],
        )
        avg_skill_level = float(np.mean(matched_levels))
    else:
        matched_levels = np.array([], dtype=int)
        avg_skill_level = 0.0

    skill_pool = DOMAIN_SKILLS[candidate_domain]
    unmatched_skills = [
        skill for skill in skill_pool if skill not in required_skills
    ]
    extra_skills = (
        rng.choice(
            unmatched_skills,
            size=min(int(rng.integers(0, 4)), len(unmatched_skills)),
            replace=False,
        ).tolist()
        if unmatched_skills
        else []
    )
    candidate_skills = list(dict.fromkeys(matched_skills + extra_skills))

    nb_projects = int(rng.poisson(1.0 + 1.5 * domain_match))
    nb_experiences = int(rng.poisson(0.6 + 0.9 * domain_match))
    study_level = int(rng.choice(STUDY_LEVELS, p=[0.16, 0.2, 0.28, 0.22, 0.14]))
    location_match = int(rng.choice([0, 1, 2], p=[0.34, 0.51, 0.15]))

    candidate_context = DOMAIN_CONTEXT[candidate_domain]
    offer_context = DOMAIN_CONTEXT[offer_domain]
    candidate_text = " ".join(
        [
            candidate_context,
            " ".join(candidate_skills),
            " ".join([candidate_context] * nb_projects),
            " ".join([candidate_context] * nb_experiences),
            STUDY_LEVEL_NAMES[study_level],
        ]
    )
    offer_text = " ".join(
        [
            offer_context,
            " ".join(required_skills.tolist()),
            "missions stage competences equipe entreprise",
        ]
    )

    features: dict[str, float | int] = {
        "skills_match_ratio": matched_count / len(required_skills),
        "nb_skills_matched": matched_count,
        "avg_skill_level": avg_skill_level,
        "domain_match": domain_match,
        "nb_projects": nb_projects,
        "nb_experiences": nb_experiences,
        "study_level": study_level,
        "location_match": location_match,
    }
    return features, candidate_text, offer_text


def calculate_weighted_score(features: dict[str, float | int], similarity: float) -> float:
    """Crée la cible synthétique depuis les poids 50/20/15/10/5 et un bruit gaussien."""
    skill_level_factor = (
        0.25 + 0.25 * float(features["avg_skill_level"])
        if features["nb_skills_matched"]
        else 0.0
    )
    skills_score = 50 * float(features["skills_match_ratio"]) * skill_level_factor
    domain_score = 20 * int(features["domain_match"])

    portfolio_count = int(features["nb_projects"]) + int(features["nb_experiences"])
    portfolio_coverage = min(portfolio_count / 4, 1)
    portfolio_score = 15 * (0.65 * portfolio_coverage + 0.35 * similarity)

    education_score = 10 * int(features["study_level"]) / 5
    location_score = 5 * int(int(features["location_match"]) > 0)
    return float(
        np.clip(
            skills_score
            + domain_score
            + portfolio_score
            + education_score
            + location_score,
            0,
            100,
        )
    )


def generate_dataset(output_path: Path, row_count: int = ROW_COUNT) -> pd.DataFrame:
    """Génère les paires avec une graine fixe pour reproduire le même CSV."""
    rng = np.random.default_rng(RANDOM_STATE)
    features_list = []
    candidate_texts = []
    offer_texts = []

    for _ in range(row_count):
        features, candidate_text, offer_text = create_candidate_offer_pair(rng)
        features_list.append(features)
        candidate_texts.append(candidate_text)
        offer_texts.append(offer_text)

    similarities = build_text_pairs(candidate_texts, offer_texts)
    rows = []
    for features, similarity in zip(features_list, similarities):
        features["tfidf_similarity"] = float(similarity)
        score = calculate_weighted_score(features, float(similarity))
        features["score"] = float(
            np.clip(score + rng.normal(loc=0, scale=5), 0, 100)
        )
        rows.append(features)

    dataset = pd.DataFrame(rows, columns=FEATURES + ["score"])
    output_path.parent.mkdir(parents=True, exist_ok=True)
    dataset.to_csv(output_path, index=False, float_format="%.4f")
    return dataset


if __name__ == "__main__":
    destination = Path(__file__).resolve().parent / "dataset.csv"
    generated = generate_dataset(destination)
    print(f"Dataset généré : {destination}")
    print(f"{len(generated)} paires candidat-offre, {len(FEATURES)} caractéristiques.")
