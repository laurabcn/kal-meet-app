-- Migration: una reunió com a molt per pista, i que ho digui la BD
--
-- El domini ja només n'admet una (`Clue` en porta una d'obligatòria), però
-- `meetings` només tenia un índex de cerca sobre `clue_id`, cap unicitat. Si mai
-- hi hagués dues files amb el mateix `clue_id`, `KalHydrator` es quedaria
-- SILENCIOSAMENT amb l'última i l'altra desapareixeria sense error ni log.
--
-- Amb el CRUD de pistes hi ha un camí d'escriptura més (`POST /kal/{id}/clue`),
-- o sigui més superfície perquè un error de programació hi arribi. La BD passa
-- a ser l'última xarxa.
--
-- Parcial (`where clue_id is not null`) perquè les reunions de KAL tenen
-- `clue_id` null i n'hi pot haver diverses: a Postgres els NULL no xoquen entre
-- ells en un índex únic, però el filtre ho deixa explícit i manté l'índex petit.

create unique index if not exists meetings_clue_id_unique
    on meetings (clue_id) where clue_id is not null;

comment on index meetings_clue_id_unique is 'A clue has at most one meeting; kal-level meetings (clue_id null) are unconstrained.';
