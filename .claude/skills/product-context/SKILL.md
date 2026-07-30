---
name: product-context
description: Roadmap per fases (MVP / Fase 2 / Fase Patterns / Fase IA) i context de negoci de KAL App — monetització, validació amb organitzadores fundadores, i el MCP de llanes com a canal d'adquisició. Usa-la per decisions de producte, de priorització o de "això on encaixa?", no per escriure codi del dia a dia.
---

# KAL App — roadmap i context de negoci

Aquest fitxer conté les decisions de PRODUCTE. Les restriccions d'abast actives
(«Funcionalitats MVP (i NOMÉS aquestes)» i «Fora de l'MVP») viuen al `CLAUDE.md`
arrel perquè s'han de veure sempre; aquí hi ha el perquè i el què vindrà després.

## Abast per fases del model

- **MVP** exposa a la UI: Kal + Clues bàsiques + PatternInfo (fitxa progressiva,
  dins de Kal, sense cercador) + Participation + Photos + reunions visuals a
  nivell de KAL + 1 aula de xat (candidata, pendent d'entrevistes)
- **Fase 2:** múltiples aules de xat per idioma, vídeos gravats per pista,
  alliberament programat amb recordatoris, reunions visuals lligades a pista +
  múltiples per idioma
- **Fase Patterns (post-MVP):** extreure PatternInfo al seu bounded context
  propi `src/Patterns/` (agregat, repositori, event de creació). Habilita la
  venda de patrons a través del producte (amb descompte a l'organitzadora que
  hi ven) i `visibility='discoverable'` per al cercador/recomanador. L'esquema
  de l'MVP ja ho preveu; l'extracció serà neta
- **Fase IA (línia pròpia, post-MVP):** assistent de creació per a
  l'organitzadora — llegir el PDF del patró per pre-omplir PatternInfo, suggerir
  descripcions i estructura de pistes, detectar llanes/gruixos. Línia
  diferenciada del recomanador, tot i compartir el corpus de patrons
  estructurats. El MCP de llanes/patrons (read-only) hi connecta

## Context de negoci (per decisions de producte)

- Validar primer gratis amb 1-2 organitzadores "fundadores"; monetitzar
  (subscripció 10-15 €/mes per organitzadora) quan hi hagi 20-30 actives
- Objectiu personal: ~500 €/mes d'ingressos extra
- La desenvolupadora és docent de la Generalitat: cal compatibilitat aprovada
  abans de generar ingressos (tràmit pendent, no bloqueja el desenvolupament)
- Via paral·lela futura: MCP de llana/patrons com a canal d'adquisició. És un
  **client read-only** de l'API del producte (només GET, mateix contracte
  OpenAPI): consulta patrons/KALs `discoverable` i dades estructurades per
  recomanar/respondre. No escriu MAI, cap acció en nom de l'usuària; auth de
  lectura amb token de servei, sense accés a contingut privat d'altri. Hi ha SDK
  de MCP per PHP; si convingués fer-lo en Python, és un mòdul petit i aïllat
- El pla complet de tasques és a Notion ("Eina KALs — Tauler de tasques")
