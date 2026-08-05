-- Migration: permisos de taula per al rol `authenticated`
--
-- Sense això les polítiques RLS de l'esquema són DECORATIVES: Postgres mira
-- primer els permisos de taula i només després avalua la RLS, o sigui que el
-- frontend amb supabase-js rebia `permission denied for table kals` abans que
-- cap `using (...)` s'arribés a executar. Cap política filtrava res perquè cap
-- s'arribava a avaluar.
--
-- Per què faltaven: hi ha dos ALTER DEFAULT PRIVILEGES a `public`. El de
-- `supabase_admin` concedeix arwdDxt a anon/authenticated/service_role; el de
-- `postgres` només Dxt (TRUNCATE, REFERENCES, TRIGGER). Les taules creades des
-- de supabase/migrations/ són propietat de `postgres`, o sigui que hereten el
-- segon i es queden sense SELECT ni INSERT. Les creades pel dashboard (que són
-- de `supabase_admin`) no tenen el problema — d'aquí que això passés
-- desapercebut.
--
-- Aquesta migració NO obre accés nou: concedeix exactament el que cada
-- política ja donava per fet. Les files les segueix filtrant la RLS; el grant
-- només deixa que la consulta hi arribi.
--
-- `anon` es queda fora expressament: tot el producte és darrere del login.

-- Lectura: totes les taules amb una política `*_select_*`.
grant select on
    kals,
    kal_locales,
    kal_files,
    clues,
    meetings,
    debate_rooms,
    participations
to authenticated;

-- Escriptura de l'organitzadora: taules amb una política `*_insert_organizer`.
grant insert on
    kals,
    kal_locales,
    kal_files,
    clues,
    meetings
to authenticated;

-- Edició de l'organitzadora: taules amb una política `*_update_organizer`.
grant update on
    kals,
    clues,
    meetings
to authenticated;

-- `participations` es queda amb SELECT i prou: no hi ha cap política d'INSERT
-- perquè apuntar-se passa pel backend, que és qui valida el token d'invitació.
-- Un INSERT directe del client ha de seguir fallant (ho prova
-- tests/Integration/Rls/ParticipationsPolicyTest.php).

-- Cap DELETE per a ningú: els kals es marquen amb `deleted_at` (soft delete),
-- no s'esborren.
