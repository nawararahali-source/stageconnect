\# Fiche d'incident : version défectueuse en environnement de test



\## Résumé

| Champ | Valeur |

|---|---|

| Date | 25/11/2026 |

| Environnement | Test (GitHub Actions + docker compose) |

| Gravité | Moyenne (environnement de test, aucun utilisateur impacté) |

| Statut | Résolu |



\## Chronologie

1\. Merge de la PR #10 (commit `0c10361`) dans `develop` : modification de la route `/api/health`.

2\. Run CI #26 : tests, sécurité et build Docker réussis ; le déploiement en test échoue.

3\. Détection par les smoke tests : `FAIL backend ne repond pas`.

4\. Diagnostic à partir des logs des conteneurs (LOG\_CHANNEL=stderr).

5\. Rollback via le workflow « Rollback (environnement de test) » vers `sha-98e2ca5` : succès, smoke tests OK.

6\. Correction par `git revert` du commit fautif (PR #13) et ajout d'un test PHPUnit sur `/api/health`.

7\. Redéploiement automatique de `develop` (run #32) : smoke tests OK.



\## Symptôme

La route `/api/health` ne répondait plus 200 : le backend ne passait pas la sonde de santé.



\## Cause racine

Le commit `0c10361` remplaçait `env('APP\_VERSION', 'dev')` par `config('app.build\_info')\['version']`. Cette clé de configuration n'existe pas, ce qui provoque une erreur 500 sur la sonde.



\## Pourquoi les tests n'ont pas détecté le bug

Aucun test automatisé ne couvrait `/api/health` : le quality gate était vert alors que le déploiement était cassé. Seuls les smoke tests post-déploiement l'ont détecté.



\## Résolution

\- Rollback (preuve : workflow Rollback #1, Summary « Version restauree : sha-98e2ca5 »).

\- Correction durable : revert du commit fautif (PR #13).



\## Actions préventives

\- Test PHPUnit `HealthCheckTest` ajouté : le quality gate bloquera désormais ce type d'erreur.

\- Conserver les smoke tests comme dernier filet de sécurité.

\- Amélioration proposée : approbation manuelle avant déploiement et déploiement progressif.

