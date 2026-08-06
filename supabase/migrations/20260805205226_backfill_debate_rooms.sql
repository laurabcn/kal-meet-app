-- Migration: aula per als KALs creats abans que el xat es cablegés
--
-- `debate_rooms` existeix des de l'esquema inicial, però `CreateKal` no hi
-- inseria res: el xat era candidat MVP i la creació es va deixar diferida a
-- propòsit (docs/specs/kal-debate.md). Ara que la participant aterra a l'aula
-- en entrar al KAL, tots n'han de tenir una — també els que ja existien.
--
-- Aquesta migració fa la meitat que el codi no pot fer; l'altra meitat (crear
-- l'aula amb el KAL) va al `CreateKalCommandHandler`.
--
-- L'ULID es genera aquí amb `generate_ulid()` i no a l'aplicació: és una
-- excepció de la mateixa família que `profiles.id`, i pel mateix motiu —
-- aquestes files neixen sense que cap codi PHP hi participi. La regla general
-- (ULIDs amb symfony/uid) segueix valent per a tot el que crea l'aplicació.
--
-- Idempotent: el `where not exists` i l'índex únic `debate_rooms_kal_id_unique`
-- fan que tornar-la a passar no dupliqui res.
--
-- Els KALs amb soft delete també en reben: `deleted_at` és reversible i deixar
-- l'aula fora obligaria a recordar de crear-la si algun dia es restaura.

insert into debate_rooms (id, kal_id)
select generate_ulid(), k.id
from kals k
where not exists (
    select 1 from debate_rooms r where r.kal_id = k.id
);
