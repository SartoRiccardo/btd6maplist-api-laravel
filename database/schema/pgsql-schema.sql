--
-- PostgreSQL database dump
--

\restrict afSV8AV97V2yuBIYA15wf0WT8VgcGJvos4Ir3ts5fFZLjdEyHyEITh00i3bwDVZ

-- Dumped from database version 16.10 (Debian 16.10-1.pgdg13+1)
-- Dumped by pg_dump version 16.10 (Debian 16.10-1.pgdg13+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: latest_maps_meta(timestamp without time zone); Type: FUNCTION; Schema: public; Owner: btd6maplist
--

CREATE FUNCTION public.latest_maps_meta(cutoff timestamp without time zone) RETURNS TABLE(id integer, code character varying, placement_curver integer, placement_allver integer, difficulty integer, optimal_heros text, botb_difficulty integer, remake_of integer, created_on timestamp without time zone, deleted_on timestamp without time zone)
    LANGUAGE sql IMMUTABLE
    AS $$
    SELECT DISTINCT ON (code)
        id,
        code,
        placement_curver,
        placement_allver,
        difficulty,
        optimal_heros,
        botb_difficulty,
        remake_of,
        created_on,
        deleted_on
    FROM map_list_meta
    WHERE created_on <= cutoff
    ORDER BY code DESC, created_on DESC;
$$;


ALTER FUNCTION public.latest_maps_meta(cutoff timestamp without time zone) OWNER TO btd6maplist;

--
-- Name: leaderboard_black_border(integer); Type: FUNCTION; Schema: public; Owner: btd6maplist
--

CREATE FUNCTION public.leaderboard_black_border(format_id integer) RETURNS TABLE(user_id bigint, score integer, placement integer)
    LANGUAGE sql IMMUTABLE
    AS $$
    WITH black_border_completions AS (
        SELECT DISTINCT c.map, lcp.user_id
        FROM completions c
        JOIN latest_completions r
            ON c.id = r.completion
        JOIN formats_rules_subsets f
            ON r.format = f.format_child AND format_id = f.format_parent
            OR r.format = format_id
        JOIN comp_players lcp
            ON r.id = lcp.run
        WHERE r.black_border
            AND r.accepted_by IS NOT NULL
            AND r.deleted_on IS NULL
    ),
    valid_maps AS MATERIALIZED (
        SELECT *
        FROM latest_maps_meta(NOW()::timestamp) m
        WHERE (
                format_id = 1 AND m.placement_curver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                OR format_id = 2 AND m.placement_allver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                OR format_id = 51 AND m.difficulty >= 0
                OR format_id = 11 AND m.remake_of IS NOT NULL
                OR format_id = 52 AND m.botb_difficulty >= 0
            )
            AND m.deleted_on IS NULL
    ),
    leaderboard AS (
        SELECT r.user_id, COUNT(*) AS score
        FROM black_border_completions r
        JOIN valid_maps m
            ON r.map = m.code
        GROUP BY r.user_id
    )
    SELECT user_id, score, RANK() OVER(ORDER BY score DESC) AS placement
    FROM leaderboard
    ORDER BY placement ASC, user_id DESC
$$;


ALTER FUNCTION public.leaderboard_black_border(format_id integer) OWNER TO btd6maplist;

--
-- Name: leaderboard_lccs(integer); Type: FUNCTION; Schema: public; Owner: btd6maplist
--

CREATE FUNCTION public.leaderboard_lccs(format_id integer) RETURNS TABLE(user_id bigint, score integer, placement integer)
    LANGUAGE sql IMMUTABLE
    AS $$
    WITH valid_lccs AS (
        SELECT DISTINCT ON (map) *
        FROM lccs_by_map lccs
        JOIN formats_rules_subsets f
            ON lccs.format = f.format_child AND format_id = f.format_parent
            OR lccs.format = format_id
        ORDER BY lccs.map DESC, lccs.leftover DESC
    ),
    valid_maps AS MATERIALIZED (
        SELECT *
        FROM latest_maps_meta(NOW()::timestamp) m
        WHERE (
                format_id = 1 AND m.placement_curver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                OR format_id = 2 AND m.placement_allver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                OR format_id = 51 AND m.difficulty >= 0
                OR format_id = 11 AND m.remake_of IS NOT NULL
                OR format_id = 52 AND m.botb_difficulty >= 0
            )
            AND m.deleted_on IS NULL
    ),
    leaderboard AS (
        SELECT lcp.user_id, COUNT(lcp.user_id) AS score
        FROM valid_lccs lccs
        JOIN latest_completions r
            ON r.lcc = lccs.id
        JOIN valid_maps m
            ON lccs.map = m.code
        JOIN comp_players lcp
            ON r.id = lcp.run
        WHERE r.accepted_by IS NOT NULL
            AND r.deleted_on IS NULL
        GROUP BY lcp.user_id
    )
    SELECT user_id, score, RANK() OVER(ORDER BY score DESC) AS placement
    FROM leaderboard
    ORDER BY placement ASC, user_id DESC
$$;


ALTER FUNCTION public.leaderboard_lccs(format_id integer) OWNER TO btd6maplist;

--
-- Name: leaderboard_no_geraldo(integer); Type: FUNCTION; Schema: public; Owner: btd6maplist
--

CREATE FUNCTION public.leaderboard_no_geraldo(format_id integer) RETURNS TABLE(user_id bigint, score integer, placement integer)
    LANGUAGE sql IMMUTABLE
    AS $$
    WITH no_geraldo_completions AS (
        SELECT DISTINCT c.map, lcp.user_id
        FROM completions c
        JOIN latest_completions r
            ON c.id = r.completion
        JOIN formats_rules_subsets f
            ON r.format = f.format_child AND format_id = f.format_parent
            OR r.format = format_id
        JOIN comp_players lcp
            ON r.id = lcp.run
        WHERE r.no_geraldo
            AND r.accepted_by IS NOT NULL
            AND r.deleted_on IS NULL
    ),
    valid_maps AS MATERIALIZED (
        SELECT *
        FROM latest_maps_meta(NOW()::timestamp) m
        WHERE (
                format_id = 1 AND m.placement_curver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                OR format_id = 2 AND m.placement_allver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                OR format_id = 51 AND m.difficulty >= 0
                OR format_id = 11 AND m.remake_of IS NOT NULL
                OR format_id = 52 AND m.botb_difficulty >= 0
            )
            AND m.deleted_on IS NULL
    ),
    leaderboard AS (
        SELECT r.user_id, COUNT(*) AS score
        FROM no_geraldo_completions r
        JOIN valid_maps m
            ON r.map = m.code
        GROUP BY r.user_id
    )
    SELECT user_id, score, RANK() OVER(ORDER BY score DESC) AS placement
    FROM leaderboard
    ORDER BY placement ASC, user_id DESC
$$;


ALTER FUNCTION public.leaderboard_no_geraldo(format_id integer) OWNER TO btd6maplist;

--
-- Name: refresh_listmap_points(); Type: FUNCTION; Schema: public; Owner: btd6maplist
--

CREATE FUNCTION public.refresh_listmap_points() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    REFRESH MATERIALIZED VIEW listmap_points;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.refresh_listmap_points() OWNER TO btd6maplist;

--
-- Name: set_comp_as_verification(integer); Type: PROCEDURE; Schema: public; Owner: btd6maplist
--

CREATE PROCEDURE public.set_comp_as_verification(IN comp_meta_id integer)
    LANGUAGE plpgsql
    AS $$
DECLARE
    comp_id INT;
    map_code VARCHAR(10);
    current_btd6_ver INT;
    is_verified BOOLEAN;
BEGIN
    SELECT completion INTO comp_id
    FROM completions_meta
    WHERE id = comp_meta_id;

    SELECT c.map INTO map_code
    FROM completions c
    WHERE c.id = comp_id;

    SELECT c.value::int INTO current_btd6_ver
    FROM config c
    WHERE c.name = 'current_btd6_ver';

    -- Current version verifier

    SELECT COUNT(*) > 0 INTO is_verified
    FROM verifications
    WHERE version = current_btd6_ver
        AND map = map_code;

    IF NOT is_verified THEN
        INSERT INTO verifications (map, user_id, version)
        SELECT map_code, user_id, current_btd6_ver
        FROM comp_players
        WHERE run = comp_meta_id;
    END IF;

    -- First time verifier

    SELECT COUNT(*) > 0 INTO is_verified
    FROM verifications
    WHERE version IS NULL
        AND map = map_code;

    IF NOT is_verified THEN
        INSERT INTO verifications (map, user_id, version)
        SELECT map_code, user_id, NULL
        FROM comp_players
        WHERE run = comp_meta_id;
    END IF;
END;
$$;


ALTER PROCEDURE public.set_comp_as_verification(IN comp_meta_id integer) OWNER TO btd6maplist;

--
-- Name: set_verif_on_accept(); Type: FUNCTION; Schema: public; Owner: btd6maplist
--

CREATE FUNCTION public.set_verif_on_accept() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF (TG_OP = 'INSERT' OR OLD.accepted_by IS NULL) THEN
        CALL set_comp_as_verification(NEW.id);
    END IF;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.set_verif_on_accept() OWNER TO btd6maplist;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: achievement_roles; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.achievement_roles (
    lb_format integer NOT NULL,
    lb_type character varying(16) NOT NULL,
    threshold integer DEFAULT 0 NOT NULL,
    for_first boolean DEFAULT false NOT NULL,
    tooltip_description character varying(128),
    name character varying(32) NOT NULL,
    clr_border integer DEFAULT 0 NOT NULL,
    clr_inner integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.achievement_roles OWNER TO btd6maplist;

--
-- Name: additional_codes; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.additional_codes (
    code character varying(10) NOT NULL,
    description text NOT NULL,
    belongs_to character varying(10) NOT NULL
);


ALTER TABLE public.additional_codes OWNER TO btd6maplist;

--
-- Name: comp_players; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.comp_players (
    user_id bigint NOT NULL,
    run integer NOT NULL
);


ALTER TABLE public.comp_players OWNER TO btd6maplist;

--
-- Name: completions; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.completions (
    id integer NOT NULL,
    map character varying(10) NOT NULL,
    submitted_on timestamp without time zone DEFAULT now(),
    subm_notes text,
    subm_wh_payload text,
    copied_from_id integer
);


ALTER TABLE public.completions OWNER TO btd6maplist;

--
-- Name: completions_meta; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.completions_meta (
    id integer NOT NULL,
    completion integer NOT NULL,
    black_border boolean,
    no_geraldo boolean,
    lcc integer,
    created_on timestamp without time zone DEFAULT now(),
    deleted_on timestamp without time zone,
    accepted_by bigint,
    format integer NOT NULL,
    copied_from_id integer
);


ALTER TABLE public.completions_meta OWNER TO btd6maplist;

--
-- Name: config; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.config (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    value text,
    type character varying(255),
    new_version integer,
    created_on timestamp without time zone DEFAULT now(),
    difficulty integer,
    description character varying(255)
);


ALTER TABLE public.config OWNER TO btd6maplist;

--
-- Name: latest_completions; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.latest_completions AS
 SELECT DISTINCT ON (completion) id,
    completion,
    black_border,
    no_geraldo,
    lcc,
    created_on,
    deleted_on,
    accepted_by,
    format,
    copied_from_id
   FROM public.completions_meta
  ORDER BY completion DESC, created_on DESC;


ALTER VIEW public.latest_completions OWNER TO btd6maplist;

--
-- Name: leastcostchimps; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.leastcostchimps (
    id integer NOT NULL,
    leftover integer NOT NULL
);


ALTER TABLE public.leastcostchimps OWNER TO btd6maplist;

--
-- Name: lccs_by_map; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.lccs_by_map AS
 SELECT DISTINCT ON (c.map, cm.format) c.map,
    cm.format,
    lcc.leftover,
    lcc.id
   FROM ((public.latest_completions cm
     JOIN public.completions c ON ((c.id = cm.completion)))
     JOIN public.leastcostchimps lcc ON ((cm.lcc = lcc.id)))
  WHERE ((cm.accepted_by IS NOT NULL) AND (cm.deleted_on IS NULL))
  ORDER BY c.map, cm.format, lcc.leftover DESC, c.submitted_on;


ALTER VIEW public.lccs_by_map OWNER TO btd6maplist;

--
-- Name: leaderboard_experts_points; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.leaderboard_experts_points AS
 WITH config_values AS (
         SELECT ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'exp_bb_multi'::text)) AS exp_bb_multi,
            ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'exp_lcc_extra'::text)) AS exp_lcc_extra
        ), expert_maps AS MATERIALIZED (
         SELECT m.code,
            (c1.value)::integer AS points,
            (c2.value)::integer AS extra_nogerry,
            cv.exp_bb_multi,
            cv.exp_lcc_extra
           FROM (((public.latest_maps_meta((now())::timestamp without time zone) m(id, code, placement_curver, placement_allver, difficulty, optimal_heros, botb_difficulty, remake_of, created_on, deleted_on)
             JOIN public.config c1 ON (((m.difficulty = c1.difficulty) AND ((c1.name)::text ~~ 'exp_points_%'::text))))
             JOIN public.config c2 ON (((m.difficulty = c2.difficulty) AND ((c2.name)::text ~~ 'exp_nogerry_points_%'::text))))
             CROSS JOIN config_values cv)
        ), completions_with_flags AS (
         SELECT cm.id AS comp_meta_id,
            lc.map,
            cm.no_geraldo,
            cm.black_border,
            ((lccs.id IS NOT NULL) AND (lbm.id = lccs.id)) AS current_lcc
           FROM (((public.completions lc
             JOIN public.latest_completions cm ON ((lc.id = cm.completion)))
             LEFT JOIN public.leastcostchimps lccs ON ((lccs.id = cm.lcc)))
             LEFT JOIN public.lccs_by_map lbm ON (((lbm.map)::text = (lc.map)::text)))
          WHERE ((((cm.format >= 51) AND (cm.format <= 100)) OR (cm.format = 1)) AND (cm.accepted_by IS NOT NULL) AND (cm.deleted_on IS NULL))
        ), completion_points AS (
         SELECT c.map,
            ply.user_id,
            bool_or(c.no_geraldo) AS no_geraldo,
            bool_or(c.black_border) AS black_border,
            bool_or(c.current_lcc) AS current_lcc
           FROM (completions_with_flags c
             JOIN public.comp_players ply ON ((c.comp_meta_id = ply.run)))
          GROUP BY c.map, ply.user_id
        ), leaderboard AS (
         SELECT cp.user_id,
            sum(((((m.points)::double precision *
                CASE
                    WHEN cp.black_border THEN m.exp_bb_multi
                    ELSE (1)::double precision
                END) + (
                CASE
                    WHEN cp.no_geraldo THEN m.extra_nogerry
                    ELSE 0
                END)::double precision) +
                CASE
                    WHEN cp.current_lcc THEN m.exp_lcc_extra
                    ELSE (0)::double precision
                END)) AS score
           FROM (completion_points cp
             JOIN expert_maps m ON (((m.code)::text = (cp.map)::text)))
          GROUP BY cp.user_id
        )
 SELECT user_id,
    score,
    rank() OVER (ORDER BY score DESC) AS placement
   FROM leaderboard
  ORDER BY (rank() OVER (ORDER BY score DESC)), user_id DESC;


ALTER VIEW public.leaderboard_experts_points OWNER TO btd6maplist;

--
-- Name: listmap_points; Type: MATERIALIZED VIEW; Schema: public; Owner: btd6maplist
--

CREATE MATERIALIZED VIEW public.listmap_points AS
 SELECT n AS placement,
    round((((( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'points_bottom_map'::text)))::double precision * power(((( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'points_top_map'::text)))::double precision / (( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'points_bottom_map'::text)))::double precision), power(((1)::double precision + (((1 - n))::double precision / ((( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'map_count'::text)))::double precision - (1)::double precision))), (( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'formula_slope'::text)))::double precision))))::numeric, (( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'decimal_digits'::text)))::integer) AS points
   FROM generate_series(1, (( SELECT config.value
           FROM public.config
          WHERE ((config.name)::text = 'map_count'::text)))::integer) indexes(n)
  WITH NO DATA;


ALTER MATERIALIZED VIEW public.listmap_points OWNER TO btd6maplist;

--
-- Name: leaderboard_maplist_points; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.leaderboard_maplist_points AS
 WITH config_values AS (
         SELECT ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'points_multi_bb'::text)) AS points_multi_bb,
            ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'points_multi_gerry'::text)) AS points_multi_gerry,
            ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'points_extra_lcc'::text)) AS points_extra_lcc
        ), maps_points AS MATERIALIZED (
         SELECT lmp.points,
            m.code
           FROM (public.latest_maps_meta((now())::timestamp without time zone) m(id, code, placement_curver, placement_allver, difficulty, optimal_heros, botb_difficulty, remake_of, created_on, deleted_on)
             JOIN public.listmap_points lmp ON ((lmp.placement = m.placement_curver)))
          WHERE (m.deleted_on IS NULL)
        ), unique_runs AS (
         SELECT DISTINCT lcp.user_id,
            c.map,
            cm.black_border,
            cm.no_geraldo,
            (cm.lcc = lccs.id) AS current_lcc
           FROM (((public.completions c
             JOIN public.latest_completions cm ON ((c.id = cm.completion)))
             JOIN public.comp_players lcp ON ((cm.id = lcp.run)))
             LEFT JOIN public.lccs_by_map lccs ON ((((lccs.map)::text = (c.map)::text) AND (lccs.format = cm.format))))
          WHERE ((cm.format = 1) AND (cm.accepted_by IS NOT NULL) AND (cm.deleted_on IS NULL))
        ), comp_user_map_modifiers AS (
         SELECT uq.user_id,
            uq.map,
                CASE
                    WHEN bool_or((uq.black_border AND uq.no_geraldo)) THEN (cv.points_multi_bb * cv.points_multi_gerry)
                    ELSE GREATEST((
                    CASE
                        WHEN bool_or(uq.black_border) THEN cv.points_multi_bb
                        ELSE (0)::double precision
                    END +
                    CASE
                        WHEN bool_or(uq.no_geraldo) THEN cv.points_multi_gerry
                        ELSE (0)::double precision
                    END), (1)::double precision)
                END AS multiplier,
                CASE
                    WHEN bool_or(uq.current_lcc) THEN cv.points_extra_lcc
                    ELSE (0)::double precision
                END AS additive
           FROM (unique_runs uq
             CROSS JOIN config_values cv)
          GROUP BY uq.user_id, uq.map, cv.points_multi_bb, cv.points_multi_gerry, cv.points_extra_lcc
        ), user_points AS (
         SELECT modi.user_id,
            ((((mwp.points)::double precision * modi.multiplier) + modi.additive) * (
                CASE
                    WHEN (modi.user_id = '640298779643215902'::bigint) THEN '-1'::integer
                    ELSE 1
                END)::double precision) AS points
           FROM (comp_user_map_modifiers modi
             JOIN maps_points mwp ON (((modi.map)::text = (mwp.code)::text)))
        ), leaderboard AS (
         SELECT up.user_id,
            sum(up.points) AS score
           FROM user_points up
          GROUP BY up.user_id
        )
 SELECT user_id,
    score,
    rank() OVER (ORDER BY score DESC) AS placement
   FROM leaderboard
  ORDER BY (rank() OVER (ORDER BY score DESC)), user_id DESC;


ALTER VIEW public.leaderboard_maplist_points OWNER TO btd6maplist;

--
-- Name: all_leaderboards; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.all_leaderboards AS
 SELECT 1 AS lb_format,
    'points'::text AS lb_type,
    leaderboard_maplist_points.user_id,
    leaderboard_maplist_points.score,
    leaderboard_maplist_points.placement
   FROM public.leaderboard_maplist_points
UNION ALL
 SELECT 1 AS lb_format,
    'lccs'::text AS lb_type,
    leaderboard_lccs.user_id,
    leaderboard_lccs.score,
    leaderboard_lccs.placement
   FROM public.leaderboard_lccs(1) leaderboard_lccs(user_id, score, placement)
UNION ALL
 SELECT 1 AS lb_format,
    'no_geraldo'::text AS lb_type,
    leaderboard_no_geraldo.user_id,
    leaderboard_no_geraldo.score,
    leaderboard_no_geraldo.placement
   FROM public.leaderboard_no_geraldo(51) leaderboard_no_geraldo(user_id, score, placement)
UNION ALL
 SELECT 1 AS lb_format,
    'black_border'::text AS lb_type,
    leaderboard_black_border.user_id,
    leaderboard_black_border.score,
    leaderboard_black_border.placement
   FROM public.leaderboard_black_border(1) leaderboard_black_border(user_id, score, placement)
UNION ALL
 SELECT 51 AS lb_format,
    'points'::text AS lb_type,
    leaderboard_experts_points.user_id,
    leaderboard_experts_points.score,
    leaderboard_experts_points.placement
   FROM public.leaderboard_experts_points
UNION ALL
 SELECT 51 AS lb_format,
    'lccs'::text AS lb_type,
    leaderboard_lccs.user_id,
    leaderboard_lccs.score,
    leaderboard_lccs.placement
   FROM public.leaderboard_lccs(51) leaderboard_lccs(user_id, score, placement)
UNION ALL
 SELECT 51 AS lb_format,
    'no_geraldo'::text AS lb_type,
    leaderboard_no_geraldo.user_id,
    leaderboard_no_geraldo.score,
    leaderboard_no_geraldo.placement
   FROM public.leaderboard_no_geraldo(51) leaderboard_no_geraldo(user_id, score, placement)
UNION ALL
 SELECT 51 AS lb_format,
    'black_border'::text AS lb_type,
    leaderboard_black_border.user_id,
    leaderboard_black_border.score,
    leaderboard_black_border.placement
   FROM public.leaderboard_black_border(51) leaderboard_black_border(user_id, score, placement);


ALTER VIEW public.all_leaderboards OWNER TO btd6maplist;

--
-- Name: completion_proofs; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.completion_proofs (
    run integer NOT NULL,
    proof_url text NOT NULL,
    proof_type integer
);


ALTER TABLE public.completion_proofs OWNER TO btd6maplist;

--
-- Name: completions_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.completions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.completions_id_seq OWNER TO btd6maplist;

--
-- Name: completions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.completions_id_seq OWNED BY public.completions.id;


--
-- Name: completions_meta_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.completions_meta_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.completions_meta_id_seq OWNER TO btd6maplist;

--
-- Name: completions_meta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.completions_meta_id_seq OWNED BY public.completions_meta.id;


--
-- Name: config_formats; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.config_formats (
    config_name character varying(255),
    format_id integer
);


ALTER TABLE public.config_formats OWNER TO btd6maplist;

--
-- Name: config_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.config_id_seq OWNER TO btd6maplist;

--
-- Name: config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.config_id_seq OWNED BY public.config.id;


--
-- Name: creators; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.creators (
    user_id bigint NOT NULL,
    role text,
    map character varying(10) NOT NULL
);


ALTER TABLE public.creators OWNER TO btd6maplist;

--
-- Name: discord_roles; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.discord_roles (
    ar_lb_format integer NOT NULL,
    ar_lb_type character varying(16) NOT NULL,
    ar_threshold integer DEFAULT 0 NOT NULL,
    guild_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.discord_roles OWNER TO btd6maplist;

--
-- Name: formats; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.formats (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    map_submission_wh text,
    run_submission_wh text,
    hidden boolean DEFAULT true NOT NULL,
    run_submission_status integer DEFAULT 0 NOT NULL,
    map_submission_status integer DEFAULT 0 NOT NULL,
    emoji character varying(255)
);


ALTER TABLE public.formats OWNER TO btd6maplist;

--
-- Name: formats_rules_subsets; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.formats_rules_subsets (
    format_parent integer NOT NULL,
    format_child integer NOT NULL
);


ALTER TABLE public.formats_rules_subsets OWNER TO btd6maplist;

--
-- Name: lb_linked_roles; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.lb_linked_roles AS
 WITH user_linked_roles AS (
         SELECT DISTINCT ON (lb.user_id, ar.lb_format, ar.lb_type) lb.user_id,
            ar.lb_format,
            ar.lb_type,
            ar.threshold
           FROM (public.all_leaderboards lb
             JOIN public.achievement_roles ar ON (((lb.lb_format = ar.lb_format) AND (lb.lb_type = (ar.lb_type)::text))))
          WHERE (((lb.score >= (ar.threshold)::double precision) AND (NOT ar.for_first)) OR ((lb.placement = 1) AND ar.for_first))
          ORDER BY lb.user_id, ar.lb_format, ar.lb_type, ar.for_first DESC, ar.threshold DESC
        )
 SELECT ulr.user_id,
    dr.guild_id,
    dr.role_id
   FROM (user_linked_roles ulr
     JOIN public.discord_roles dr ON (((ulr.lb_format = dr.ar_lb_format) AND ((ulr.lb_type)::text = (dr.ar_lb_type)::text) AND (ulr.threshold = dr.ar_threshold))));


ALTER VIEW public.lb_linked_roles OWNER TO btd6maplist;

--
-- Name: leaderboard_maplist_all_points; Type: VIEW; Schema: public; Owner: btd6maplist
--

CREATE VIEW public.leaderboard_maplist_all_points AS
 WITH config_values AS (
         SELECT ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'points_multi_bb'::text)) AS points_multi_bb,
            ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'points_multi_gerry'::text)) AS points_multi_gerry,
            ( SELECT (config.value)::double precision AS value
                   FROM public.config
                  WHERE ((config.name)::text = 'points_extra_lcc'::text)) AS points_extra_lcc
        ), maps_points AS MATERIALIZED (
         SELECT lmp.points,
            m.code
           FROM (public.latest_maps_meta((now())::timestamp without time zone) m(id, code, placement_curver, placement_allver, difficulty, optimal_heros, botb_difficulty, remake_of, created_on, deleted_on)
             JOIN public.listmap_points lmp ON ((lmp.placement = m.placement_curver)))
          WHERE (m.deleted_on IS NULL)
        ), unique_runs AS (
         SELECT DISTINCT lcp.user_id,
            c.map,
            r.black_border,
            r.no_geraldo,
            (r.lcc = lccs.id) AS current_lcc
           FROM (((public.completions c
             JOIN public.latest_completions r ON ((c.id = r.completion)))
             JOIN public.comp_players lcp ON ((r.id = lcp.run)))
             LEFT JOIN public.lccs_by_map lccs ON ((((lccs.map)::text = (c.map)::text) AND (lccs.format = r.format))))
          WHERE (((r.format = 1) OR (r.format = 2)) AND (r.accepted_by IS NOT NULL) AND (r.deleted_on IS NULL))
        ), comp_user_map_modifiers AS (
         SELECT uq.user_id,
            uq.map,
                CASE
                    WHEN bool_or((uq.black_border AND uq.no_geraldo)) THEN (cv.points_multi_bb * cv.points_multi_gerry)
                    ELSE GREATEST((
                    CASE
                        WHEN bool_or(uq.black_border) THEN cv.points_multi_bb
                        ELSE (0)::double precision
                    END +
                    CASE
                        WHEN bool_or(uq.no_geraldo) THEN cv.points_multi_gerry
                        ELSE (0)::double precision
                    END), (1)::double precision)
                END AS multiplier,
                CASE
                    WHEN bool_or(uq.current_lcc) THEN cv.points_extra_lcc
                    ELSE (0)::double precision
                END AS additive
           FROM (unique_runs uq
             CROSS JOIN config_values cv)
          GROUP BY uq.user_id, uq.map, cv.points_multi_bb, cv.points_multi_gerry, cv.points_extra_lcc
        ), user_points AS (
         SELECT modi.user_id,
            ((((mwp.points)::double precision * modi.multiplier) + modi.additive) * (
                CASE
                    WHEN (modi.user_id = '640298779643215902'::bigint) THEN '-1'::integer
                    ELSE 1
                END)::double precision) AS points
           FROM (comp_user_map_modifiers modi
             JOIN maps_points mwp ON (((modi.map)::text = (mwp.code)::text)))
        ), leaderboard AS (
         SELECT up.user_id,
            sum(up.points) AS score
           FROM user_points up
          GROUP BY up.user_id
        )
 SELECT user_id,
    score,
    rank() OVER (ORDER BY score DESC) AS placement
   FROM leaderboard
  ORDER BY (rank() OVER (ORDER BY score DESC)), user_id DESC;


ALTER VIEW public.leaderboard_maplist_all_points OWNER TO btd6maplist;

--
-- Name: leastcostchimps_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.leastcostchimps_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leastcostchimps_id_seq OWNER TO btd6maplist;

--
-- Name: leastcostchimps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.leastcostchimps_id_seq OWNED BY public.leastcostchimps.id;


--
-- Name: map_aliases; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.map_aliases (
    alias character varying(255) NOT NULL,
    map character varying(10) NOT NULL
);


ALTER TABLE public.map_aliases OWNER TO btd6maplist;

--
-- Name: map_list_meta; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.map_list_meta (
    id integer NOT NULL,
    code character varying(10) NOT NULL,
    placement_curver integer,
    placement_allver integer,
    difficulty integer,
    optimal_heros text DEFAULT ''::text NOT NULL,
    botb_difficulty integer,
    remake_of integer,
    created_on timestamp without time zone DEFAULT now(),
    deleted_on timestamp without time zone
);


ALTER TABLE public.map_list_meta OWNER TO btd6maplist;

--
-- Name: map_list_meta_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.map_list_meta_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.map_list_meta_id_seq OWNER TO btd6maplist;

--
-- Name: map_list_meta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.map_list_meta_id_seq OWNED BY public.map_list_meta.id;


--
-- Name: map_submissions; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.map_submissions (
    code character varying(10) NOT NULL,
    submitter bigint NOT NULL,
    subm_notes text,
    format_id integer NOT NULL,
    proposed integer NOT NULL,
    rejected_by bigint,
    created_on timestamp without time zone DEFAULT now(),
    completion_proof character varying(256),
    wh_data text,
    wh_msg_id bigint,
    id integer NOT NULL
);


ALTER TABLE public.map_submissions OWNER TO btd6maplist;

--
-- Name: map_submissions_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.map_submissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.map_submissions_id_seq OWNER TO btd6maplist;

--
-- Name: map_submissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.map_submissions_id_seq OWNED BY public.map_submissions.id;


--
-- Name: maps; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.maps (
    code character varying(10) NOT NULL,
    name character varying(255) NOT NULL,
    r6_start text,
    map_data text,
    map_preview_url text,
    map_notes text
);


ALTER TABLE public.maps OWNER TO btd6maplist;

--
-- Name: mapver_compatibilities; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.mapver_compatibilities (
    version integer NOT NULL,
    status integer NOT NULL,
    map character varying(10) NOT NULL
);


ALTER TABLE public.mapver_compatibilities OWNER TO btd6maplist;

--
-- Name: retro_games; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.retro_games (
    game_id integer NOT NULL,
    category_id integer NOT NULL,
    subcategory_id integer NOT NULL,
    game_name character varying(32) NOT NULL,
    category_name character varying(32) NOT NULL,
    subcategory_name character varying(32)
);


ALTER TABLE public.retro_games OWNER TO btd6maplist;

--
-- Name: retro_maps; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.retro_maps (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    sort_order integer NOT NULL,
    preview_url text NOT NULL,
    game_id integer NOT NULL,
    category_id integer NOT NULL,
    subcategory_id integer NOT NULL
);


ALTER TABLE public.retro_maps OWNER TO btd6maplist;

--
-- Name: retro_maps_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.retro_maps_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.retro_maps_id_seq OWNER TO btd6maplist;

--
-- Name: retro_maps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.retro_maps_id_seq OWNED BY public.retro_maps.id;


--
-- Name: role_format_permissions; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.role_format_permissions (
    role_id bigint NOT NULL,
    format_id bigint,
    permission character varying(255)
);


ALTER TABLE public.role_format_permissions OWNER TO btd6maplist;

--
-- Name: role_grants; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.role_grants (
    role_required bigint NOT NULL,
    role_can_grant bigint NOT NULL
);


ALTER TABLE public.role_grants OWNER TO btd6maplist;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    assign_on_create boolean DEFAULT false,
    internal boolean DEFAULT false
);


ALTER TABLE public.roles OWNER TO btd6maplist;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: btd6maplist
--

CREATE SEQUENCE public.roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO btd6maplist;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: btd6maplist
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: snapshot_lb_linked_roles; Type: MATERIALIZED VIEW; Schema: public; Owner: btd6maplist
--

CREATE MATERIALIZED VIEW public.snapshot_lb_linked_roles AS
 SELECT user_id,
    guild_id,
    role_id
   FROM public.lb_linked_roles
  WITH NO DATA;


ALTER MATERIALIZED VIEW public.snapshot_lb_linked_roles OWNER TO btd6maplist;

--
-- Name: user_roles; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.user_roles (
    user_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.user_roles OWNER TO btd6maplist;

--
-- Name: users; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.users (
    discord_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    nk_oak character varying(255),
    has_seen_popup boolean DEFAULT false,
    is_banned boolean DEFAULT false
);


ALTER TABLE public.users OWNER TO btd6maplist;

--
-- Name: verifications; Type: TABLE; Schema: public; Owner: btd6maplist
--

CREATE TABLE public.verifications (
    user_id bigint NOT NULL,
    version integer,
    map character varying(10) NOT NULL
);


ALTER TABLE public.verifications OWNER TO btd6maplist;

--
-- Name: completions id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions ALTER COLUMN id SET DEFAULT nextval('public.completions_id_seq'::regclass);


--
-- Name: completions_meta id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions_meta ALTER COLUMN id SET DEFAULT nextval('public.completions_meta_id_seq'::regclass);


--
-- Name: config id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.config ALTER COLUMN id SET DEFAULT nextval('public.config_id_seq'::regclass);


--
-- Name: leastcostchimps id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.leastcostchimps ALTER COLUMN id SET DEFAULT nextval('public.leastcostchimps_id_seq'::regclass);


--
-- Name: map_list_meta id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_list_meta ALTER COLUMN id SET DEFAULT nextval('public.map_list_meta_id_seq'::regclass);


--
-- Name: map_submissions id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_submissions ALTER COLUMN id SET DEFAULT nextval('public.map_submissions_id_seq'::regclass);


--
-- Name: retro_maps id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.retro_maps ALTER COLUMN id SET DEFAULT nextval('public.retro_maps_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: achievement_roles achievement_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.achievement_roles
    ADD CONSTRAINT achievement_roles_pkey PRIMARY KEY (lb_format, lb_type, threshold);


--
-- Name: achievement_roles achievement_roles_uq_1; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.achievement_roles
    ADD CONSTRAINT achievement_roles_uq_1 UNIQUE (lb_format, lb_type, threshold);


--
-- Name: additional_codes additional_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.additional_codes
    ADD CONSTRAINT additional_codes_pkey PRIMARY KEY (code);


--
-- Name: completions_meta completions_meta_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions_meta
    ADD CONSTRAINT completions_meta_pkey PRIMARY KEY (id);


--
-- Name: config config_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.config
    ADD CONSTRAINT config_pkey PRIMARY KEY (id);


--
-- Name: discord_roles discord_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.discord_roles
    ADD CONSTRAINT discord_roles_pkey PRIMARY KEY (role_id);


--
-- Name: formats formats_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.formats
    ADD CONSTRAINT formats_pkey PRIMARY KEY (id);


--
-- Name: formats_rules_subsets formats_rules_subsets_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.formats_rules_subsets
    ADD CONSTRAINT formats_rules_subsets_pkey PRIMARY KEY (format_parent, format_child);


--
-- Name: leastcostchimps leastcostchimps_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.leastcostchimps
    ADD CONSTRAINT leastcostchimps_pkey PRIMARY KEY (id);


--
-- Name: completions list_completions_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions
    ADD CONSTRAINT list_completions_pkey PRIMARY KEY (id);


--
-- Name: map_aliases map_aliases_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_aliases
    ADD CONSTRAINT map_aliases_pkey PRIMARY KEY (alias);


--
-- Name: map_list_meta map_list_meta_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_list_meta
    ADD CONSTRAINT map_list_meta_pkey PRIMARY KEY (id);


--
-- Name: map_submissions map_submissions_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_submissions
    ADD CONSTRAINT map_submissions_pkey PRIMARY KEY (id);


--
-- Name: maps maps_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.maps
    ADD CONSTRAINT maps_pkey PRIMARY KEY (code);


--
-- Name: retro_games retro_games_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.retro_games
    ADD CONSTRAINT retro_games_pkey PRIMARY KEY (game_id, category_id, subcategory_id);


--
-- Name: retro_maps retro_maps_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.retro_maps
    ADD CONSTRAINT retro_maps_pkey PRIMARY KEY (id);


--
-- Name: role_grants role_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.role_grants
    ADD CONSTRAINT role_grants_pkey PRIMARY KEY (role_required, role_can_grant);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: users uq_name; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT uq_name UNIQUE (name);


--
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (user_id, role_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (discord_id);


--
-- Name: config_uq_name; Type: INDEX; Schema: public; Owner: btd6maplist
--

CREATE UNIQUE INDEX config_uq_name ON public.config USING btree (name, COALESCE(new_version, '-1'::integer));


--
-- Name: idx_comp_players_user_id; Type: INDEX; Schema: public; Owner: btd6maplist
--

CREATE INDEX idx_comp_players_user_id ON public.comp_players USING btree (user_id);


--
-- Name: idx_compmeta_completion_creation; Type: INDEX; Schema: public; Owner: btd6maplist
--

CREATE INDEX idx_compmeta_completion_creation ON public.completions_meta USING btree (completion, created_on);


--
-- Name: idx_config_difficulty; Type: INDEX; Schema: public; Owner: btd6maplist
--

CREATE INDEX idx_config_difficulty ON public.config USING btree (difficulty);


--
-- Name: idx_mapmeta_code_creation; Type: INDEX; Schema: public; Owner: btd6maplist
--

CREATE INDEX idx_mapmeta_code_creation ON public.map_list_meta USING btree (code, created_on);


--
-- Name: idx_mapmeta_difficulty; Type: INDEX; Schema: public; Owner: btd6maplist
--

CREATE INDEX idx_mapmeta_difficulty ON public.map_list_meta USING btree (difficulty);


--
-- Name: config refresh_listmap_points; Type: TRIGGER; Schema: public; Owner: btd6maplist
--

CREATE TRIGGER refresh_listmap_points AFTER UPDATE ON public.config FOR EACH ROW EXECUTE FUNCTION public.refresh_listmap_points();


--
-- Name: completions_meta tr_set_verif_on_accept; Type: TRIGGER; Schema: public; Owner: btd6maplist
--

CREATE TRIGGER tr_set_verif_on_accept AFTER INSERT OR UPDATE ON public.completions_meta FOR EACH ROW WHEN (((new.accepted_by IS NOT NULL) AND (new.format = ANY (ARRAY[1, 51])))) EXECUTE FUNCTION public.set_verif_on_accept();


--
-- Name: discord_roles achievement_roles_fk_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.discord_roles
    ADD CONSTRAINT achievement_roles_fk_1 FOREIGN KEY (ar_lb_format, ar_lb_type, ar_threshold) REFERENCES public.achievement_roles(lb_format, lb_type, threshold) ON DELETE CASCADE;


--
-- Name: completions_meta fk_completions_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions_meta
    ADD CONSTRAINT fk_completions_1 FOREIGN KEY (completion) REFERENCES public.completions(id) ON DELETE CASCADE;


--
-- Name: comp_players fk_completions_meta_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.comp_players
    ADD CONSTRAINT fk_completions_meta_1 FOREIGN KEY (run) REFERENCES public.completions_meta(id) ON DELETE CASCADE;


--
-- Name: config fk_config_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.config
    ADD CONSTRAINT fk_config_1 FOREIGN KEY (new_version) REFERENCES public.config(id) ON DELETE SET NULL;


--
-- Name: map_submissions fk_formats_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_submissions
    ADD CONSTRAINT fk_formats_1 FOREIGN KEY (format_id) REFERENCES public.formats(id);


--
-- Name: config_formats fk_formats_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.config_formats
    ADD CONSTRAINT fk_formats_1 FOREIGN KEY (format_id) REFERENCES public.formats(id);


--
-- Name: formats_rules_subsets fk_formats_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.formats_rules_subsets
    ADD CONSTRAINT fk_formats_1 FOREIGN KEY (format_parent) REFERENCES public.formats(id);


--
-- Name: formats_rules_subsets fk_formats_2; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.formats_rules_subsets
    ADD CONSTRAINT fk_formats_2 FOREIGN KEY (format_child) REFERENCES public.formats(id);


--
-- Name: completions_meta fk_lccs_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions_meta
    ADD CONSTRAINT fk_lccs_1 FOREIGN KEY (lcc) REFERENCES public.leastcostchimps(id) ON DELETE SET NULL;


--
-- Name: completion_proofs fk_list_completions_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completion_proofs
    ADD CONSTRAINT fk_list_completions_1 FOREIGN KEY (run) REFERENCES public.completions(id) ON DELETE CASCADE;


--
-- Name: map_aliases fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_aliases
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (map) REFERENCES public.maps(code) ON DELETE CASCADE;


--
-- Name: additional_codes fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.additional_codes
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (belongs_to) REFERENCES public.maps(code) ON DELETE CASCADE;


--
-- Name: creators fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.creators
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (map) REFERENCES public.maps(code) ON DELETE CASCADE;


--
-- Name: verifications fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.verifications
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (map) REFERENCES public.maps(code) ON DELETE CASCADE;


--
-- Name: mapver_compatibilities fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.mapver_compatibilities
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (map) REFERENCES public.maps(code) ON DELETE CASCADE;


--
-- Name: map_list_meta fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_list_meta
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (code) REFERENCES public.maps(code);


--
-- Name: completions fk_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.completions
    ADD CONSTRAINT fk_maps_1 FOREIGN KEY (map) REFERENCES public.maps(code) ON DELETE CASCADE;


--
-- Name: retro_maps fk_retro_games_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.retro_maps
    ADD CONSTRAINT fk_retro_games_1 FOREIGN KEY (game_id, category_id, subcategory_id) REFERENCES public.retro_games(game_id, category_id, subcategory_id);


--
-- Name: map_list_meta fk_retro_maps_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_list_meta
    ADD CONSTRAINT fk_retro_maps_1 FOREIGN KEY (remake_of) REFERENCES public.retro_maps(id);


--
-- Name: role_grants fk_roles_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.role_grants
    ADD CONSTRAINT fk_roles_1 FOREIGN KEY (role_required) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: user_roles fk_roles_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT fk_roles_1 FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: role_grants fk_roles_2; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.role_grants
    ADD CONSTRAINT fk_roles_2 FOREIGN KEY (role_can_grant) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: creators fk_users_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.creators
    ADD CONSTRAINT fk_users_1 FOREIGN KEY (user_id) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: verifications fk_users_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.verifications
    ADD CONSTRAINT fk_users_1 FOREIGN KEY (user_id) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: comp_players fk_users_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.comp_players
    ADD CONSTRAINT fk_users_1 FOREIGN KEY (user_id) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: map_submissions fk_users_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_submissions
    ADD CONSTRAINT fk_users_1 FOREIGN KEY (submitter) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: user_roles fk_users_1; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT fk_users_1 FOREIGN KEY (user_id) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: map_submissions fk_users_2; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.map_submissions
    ADD CONSTRAINT fk_users_2 FOREIGN KEY (rejected_by) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: user_roles user_roles_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: user_roles user_roles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: btd6maplist
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(discord_id) ON DELETE CASCADE;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;

--
-- PostgreSQL database dump complete
--

\unrestrict afSV8AV97V2yuBIYA15wf0WT8VgcGJvos4Ir3ts5fFZLjdEyHyEITh00i3bwDVZ

