"""Exporte les candidatures acceptées/refusées avec les caractéristiques du matching."""

import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path

import pandas as pd

from generate_dataset import FEATURES, build_text_pairs


STATUS_SCORES = {"accepted": 100.0, "rejected": 0.0}
SKILL_LEVELS = {
    "debutant": 1,
    "intermediaire": 2,
    "avance": 3,
}
STUDY_LEVELS = {"l1": 1, "l2": 2, "l3": 3, "m1": 4, "m2": 5}


def normalize_text(value: str | None) -> str:
    """Normalise les accents et séparateurs pour comparer les libellés."""
    text = unicodedata.normalize("NFKD", value or "")
    text = "".join(character for character in text if not unicodedata.combining(character))
    return re.sub(r"[^a-z0-9]+", " ", text.lower()).strip()


def read_laravel_env(env_path: Path) -> dict[str, str]:
    """Lit uniquement les paramètres nécessaires sans les afficher."""
    if not env_path.exists():
        raise FileNotFoundError(f"Fichier de configuration Laravel introuvable : {env_path}")

    config = {}
    for line in env_path.read_text(encoding="utf-8").splitlines():
        match = re.match(r"^\s*(DB_[A-Z0-9_]+)\s*=\s*(.*)\s*$", line)
        if not match:
            continue
        key, value = match.groups()
        config[key] = value.strip().strip("\"'")
    return config


def study_level_value(level: str | None) -> int:
    """Encode L1, L2, L3, M1, M2 en valeurs ordinales."""
    normalized = normalize_text(level)
    match = re.search(r"\b(m[12]|l[123])\b", normalized)
    if match:
        return STUDY_LEVELS[match.group(1)]
    if "master" in normalized:
        return STUDY_LEVELS["m1"]
    if "licence" in normalized or "bachelor" in normalized:
        return STUDY_LEVELS["l3"]
    return STUDY_LEVELS["l1"]


def decode_json_array(value: str | list | None) -> list:
    if isinstance(value, list):
        return value
    if not value:
        return []
    try:
        decoded = json.loads(value)
    except (TypeError, json.JSONDecodeError):
        return []
    return decoded if isinstance(decoded, list) else []


def make_candidate_text(row: dict, skills: list[str], projects: list, experiences: list) -> str:
    portfolio_text = " ".join(
        str(item.get(key, ""))
        for item in projects + experiences
        if isinstance(item, dict)
        for key in ("nom", "poste", "technologies", "description")
    )
    return " ".join(
        [
            row.get("specialite") or "",
            row.get("formation") or "",
            row.get("bio") or "",
            " ".join(skills),
            portfolio_text,
        ]
    )


def extract_features(rows: list[dict]) -> pd.DataFrame:
    """Transforme les candidatures SQL en variables identiques au CSV synthétique."""
    candidates = []
    offers = []
    features_list = []
    labels = []

    for row in rows:
        required_skills = decode_json_array(row.get("required_skills"))
        student_skills = decode_json_array(row.get("student_skills"))
        student_skill_map = {
            normalize_text(skill.get("name")): SKILL_LEVELS.get(
                normalize_text(skill.get("level")), 1
            )
            for skill in student_skills
            if isinstance(skill, dict)
        }

        matched_levels = [
            student_skill_map[normalize_text(skill)]
            for skill in required_skills
            if normalize_text(skill) in student_skill_map
        ]
        required_count = len(required_skills)
        matched_count = len(matched_levels)
        profile_domains = [
            normalize_text(row.get("specialite")),
            normalize_text(row.get("formation")),
        ]
        offer_domain = normalize_text(row.get("domain"))
        domain_match = int(
            bool(offer_domain)
            and any(
                domain and (domain in offer_domain or offer_domain in domain)
                for domain in profile_domains
            )
        )
        projects = decode_json_array(row.get("projects"))
        experiences = decode_json_array(row.get("experiences"))

        if row.get("work_mode") == "remote":
            location_match = 2
        elif normalize_text(row.get("city")) == normalize_text(row.get("location")):
            location_match = 1
        else:
            location_match = 0

        candidates.append(
            make_candidate_text(row, list(student_skill_map), projects, experiences)
        )
        offers.append(
            " ".join(
                [
                    row.get("domain") or "",
                    " ".join(str(skill) for skill in required_skills),
                    row.get("offer_description") or "",
                ]
            )
        )
        features_list.append(
            {
                "skills_match_ratio": matched_count / required_count if required_count else 0,
                "nb_skills_matched": matched_count,
                "avg_skill_level": (
                    sum(matched_levels) / matched_count if matched_count else 0
                ),
                "domain_match": domain_match,
                "nb_projects": len(projects),
                "nb_experiences": len(experiences),
                "study_level": study_level_value(
                    row.get("niveau") or row.get("formation")
                ),
                "location_match": location_match,
            }
        )
        labels.append(STATUS_SCORES[row["status"]])

    similarities = build_text_pairs(candidates, offers)
    for features, similarity, label in zip(features_list, similarities, labels):
        features["tfidf_similarity"] = float(similarity)
        features["score"] = label

    return pd.DataFrame(features_list, columns=FEATURES + ["score"])


def fetch_applications(env_path: Path) -> list[dict]:
    """Charge les seules données nécessaires aux candidatures labellisées."""
    config = read_laravel_env(env_path)
    if config.get("DB_CONNECTION", "mysql") != "mysql":
        raise ValueError("L'export exige DB_CONNECTION=mysql dans le fichier backend/.env.")

    try:
        import pymysql
    except ImportError as error:
        raise RuntimeError(
            "PyMySQL manque. Installez les dépendances avec : pip install -r requirements.txt"
        ) from error

    query = """
        SELECT
            a.id AS application_id,
            a.status,
            o.domain,
            o.description AS offer_description,
            o.required_skills,
            o.work_mode,
            o.location,
            p.specialite,
            p.formation,
            p.niveau,
            p.ville AS city,
            p.bio,
            p.projects,
            p.experiences,
            (
                SELECT JSON_ARRAYAGG(JSON_OBJECT('name', s.name, 'level', s.level))
                FROM skills AS s
                WHERE s.user_id = u.id
            ) AS student_skills
        FROM applications AS a
        INNER JOIN users AS u ON u.id = a.student_id
        INNER JOIN internship_offers AS o ON o.id = a.internship_offer_id
        LEFT JOIN profiles AS p ON p.user_id = u.id
        WHERE a.status IN ('accepted', 'rejected')
        ORDER BY a.id
    """
    connection = pymysql.connect(
        host=config.get("DB_HOST", "127.0.0.1"),
        port=int(config.get("DB_PORT", "3306")),
        user=config.get("DB_USERNAME", "root"),
        password=config.get("DB_PASSWORD", ""),
        database=config.get("DB_DATABASE", ""),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        with connection.cursor() as cursor:
            cursor.execute(query)
            return list(cursor.fetchall())
    finally:
        connection.close()


def export(output_path: Path, env_path: Path, append: bool = False) -> int:
    """Exporte les candidatures réelles, avec possibilité d'ajout au dataset."""
    rows = fetch_applications(env_path)
    if not rows:
        raise ValueError("Aucune candidature acceptée ou refusée n'a été trouvée.")

    real_data = extract_features(rows)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    if append and output_path.exists():
        synthetic_data = pd.read_csv(output_path)
        combined_data = pd.concat([synthetic_data, real_data], ignore_index=True)
        combined_data.to_csv(output_path, index=False, float_format="%.4f")
    else:
        real_data.to_csv(output_path, index=False, float_format="%.4f")

    return len(real_data)


def main() -> int:
    service_dir = Path(__file__).resolve().parent
    project_dir = service_dir.parent
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--env",
        type=Path,
        default=project_dir / "backend" / ".env",
        help="Chemin du fichier .env Laravel (défaut : backend/.env).",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=service_dir / "real_applications.csv",
        help="Chemin du CSV exporté (défaut : real_applications.csv).",
    )
    parser.add_argument(
        "--append",
        action="store_true",
        help="Ajoute les candidatures réelles au CSV choisi au lieu de le remplacer.",
    )
    args = parser.parse_args()

    try:
        count = export(args.output, args.env, args.append)
    except (FileNotFoundError, RuntimeError, ValueError, OSError) as error:
        print(f"Erreur d'export : {error}", file=sys.stderr)
        return 1

    print(f"{count} candidatures exportées dans {args.output}")
    if args.append:
        print("Les résultats acceptés/refusés sont encodés en scores 100/0.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
