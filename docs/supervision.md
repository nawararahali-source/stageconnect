\# Guide de supervision et de diagnostic



\## Sondes de sante

| Service | URL | Reponse attendue |

|---|---|---|

| Backend | /api/health | 200 + JSON {status, database, version, time} ; 503 si la base est indisponible |

| ML service | /health | 200 |

| Frontend | /health | 200 |



La version deployee est visible dans le champ `version` de /api/health

(elle correspond au tag de l'image, ex: sha-98e2ca5).



\## Ou consulter les logs

\- Pipeline : GitHub > Actions > run > job "Deploiement en test" (steps Smoke tests et Logs des conteneurs).

\- Conteneurs : `docker compose -f docker-compose.test.yml logs backend` (le backend ecrit sur stderr, LOG\_CHANNEL=stderr).

\- Rapports : artefacts du run (rapports-backend, rapports-securite, frontend-dist).



\## Indicateurs techniques suivis

\- Etat du service (health check) : OK / degrade.

\- Disponibilite : resultat des smoke tests apres chaque deploiement.

\- Duree des pipelines et taux de reussite : onglet Actions.

\- Couverture de tests : rapport coverage.xml.

\- Vulnerabilites : rapports d'audit (artefact rapports-securite).



\## Diagnostic d'un incident

1\. Identifier l'etape en echec dans le run (smoke tests, tests, build).

2\. Lire "Logs des conteneurs" : chercher les lignes `ERROR`.

3\. Verifier la reponse de /api/health (champ database).

4\. Decider : correction rapide ou rollback.



\## Procedure de rollback

1\. GitHub > Actions > "Rollback (environnement de test)" > Run workflow.

2\. Saisir le tag stable (derniere version dont les smoke tests sont OK, ex: sha-98e2ca5).

3\. Verifier que le job est vert et que le Summary indique la version restauree.

4\. Corriger le code via une Pull Request, puis redeployer.

