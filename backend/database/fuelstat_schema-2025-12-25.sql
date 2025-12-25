--
-- PostgreSQL database dump
--

\restrict pvfnUuATwV7kbH6ySWmmq5xqpKW7g180ZFhViuGkSfpHK6ikKKSEhqTlBTWGOKo

-- Dumped from database version 15.15
-- Dumped by pg_dump version 15.15

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
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: expenses; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.expenses (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    vehicle_id uuid NOT NULL,
    category text NOT NULL,
    amount_eur numeric(10,2) NOT NULL,
    odometer_km integer,
    occurred_at timestamp with time zone DEFAULT now() NOT NULL,
    vendor text,
    description text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT expenses_amount_eur_check CHECK ((amount_eur >= (0)::numeric)),
    CONSTRAINT expenses_odometer_km_check CHECK ((odometer_km >= 0))
);


ALTER TABLE public.expenses OWNER TO fueladmin;

--
-- Name: fillups; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.fillups (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    vehicle_id uuid NOT NULL,
    odometer_km integer NOT NULL,
    amount numeric(10,3) NOT NULL,
    total_cost_eur numeric(10,2) NOT NULL,
    station text,
    filled_at timestamp with time zone DEFAULT now() NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    is_full boolean DEFAULT true NOT NULL,
    skip_previous boolean DEFAULT false NOT NULL,
    unit text DEFAULT 'l'::text NOT NULL,
    price_per_unit numeric(10,4),
    CONSTRAINT fillups_liters_check CHECK ((amount >= (0)::numeric)),
    CONSTRAINT fillups_odometer_km_check CHECK ((odometer_km >= 0)),
    CONSTRAINT fillups_price_total_eur_check CHECK ((total_cost_eur >= (0)::numeric)),
    CONSTRAINT fillups_unit_check CHECK ((unit = ANY (ARRAY['l'::text, 'kwh'::text, 'kg'::text])))
);


ALTER TABLE public.fillups OWNER TO fueladmin;

--
-- Name: revoked_tokens; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.revoked_tokens (
    jti text NOT NULL,
    user_id uuid NOT NULL,
    revoked_at timestamp with time zone DEFAULT now() NOT NULL,
    reason text
);


ALTER TABLE public.revoked_tokens OWNER TO fueladmin;

--
-- Name: user_media; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.user_media (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    media_type text DEFAULT 'image'::text NOT NULL,
    url text NOT NULL,
    mime_type text,
    bytes integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.user_media OWNER TO fueladmin;

--
-- Name: user_token_revocations; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.user_token_revocations (
    user_id uuid NOT NULL,
    revoked_after timestamp with time zone DEFAULT now() NOT NULL,
    reason text
);


ALTER TABLE public.user_token_revocations OWNER TO fueladmin;

--
-- Name: users; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.users (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    email text NOT NULL,
    password_hash text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    username text,
    mfa_secret text,
    mfa_enabled boolean DEFAULT false NOT NULL,
    profile_image_url text,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.users OWNER TO fueladmin;

--
-- Name: vehicle_media; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.vehicle_media (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    vehicle_id uuid NOT NULL,
    user_id uuid NOT NULL,
    media_type text DEFAULT 'image'::text NOT NULL,
    url text NOT NULL,
    mime_type text,
    bytes integer,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.vehicle_media OWNER TO fueladmin;

--
-- Name: vehicles; Type: TABLE; Schema: public; Owner: fueladmin
--

CREATE TABLE public.vehicles (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    name text NOT NULL,
    fuel_type text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    brand text,
    model text,
    year smallint,
    image_url text,
    odometer_km integer,
    mileage_km integer,
    tank_capacity numeric(10,3),
    sort_order integer DEFAULT 0 NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT vehicles_fuel_type_check CHECK ((fuel_type = ANY (ARRAY['diesel'::text, 'e5'::text, 'e10'::text, 'lpg'::text, 'cng'::text, 'electric'::text, 'hybrid'::text, 'hydrogen'::text, 'other'::text]))),
    CONSTRAINT vehicles_year_check CHECK (((year IS NULL) OR ((year >= 1886) AND (year <= 2100))))
);


ALTER TABLE public.vehicles OWNER TO fueladmin;

--
-- Name: expenses expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_pkey PRIMARY KEY (id);


--
-- Name: fillups fillups_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.fillups
    ADD CONSTRAINT fillups_pkey PRIMARY KEY (id);


--
-- Name: revoked_tokens revoked_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.revoked_tokens
    ADD CONSTRAINT revoked_tokens_pkey PRIMARY KEY (jti);


--
-- Name: user_media user_media_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.user_media
    ADD CONSTRAINT user_media_pkey PRIMARY KEY (id);


--
-- Name: user_token_revocations user_token_revocations_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.user_token_revocations
    ADD CONSTRAINT user_token_revocations_pkey PRIMARY KEY (user_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: vehicle_media vehicle_media_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.vehicle_media
    ADD CONSTRAINT vehicle_media_pkey PRIMARY KEY (id);


--
-- Name: vehicles vehicles_pkey; Type: CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.vehicles
    ADD CONSTRAINT vehicles_pkey PRIMARY KEY (id);


--
-- Name: idx_expenses_user_vehicle_date; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_expenses_user_vehicle_date ON public.expenses USING btree (user_id, vehicle_id, occurred_at DESC);


--
-- Name: idx_expenses_vehicle_odo; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_expenses_vehicle_odo ON public.expenses USING btree (vehicle_id, odometer_km);


--
-- Name: idx_fillups_user_vehicle_filled; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_fillups_user_vehicle_filled ON public.fillups USING btree (user_id, vehicle_id, filled_at DESC);


--
-- Name: idx_fillups_vehicle_odo; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_fillups_vehicle_odo ON public.fillups USING btree (vehicle_id, odometer_km);


--
-- Name: idx_revoked_tokens_user; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_revoked_tokens_user ON public.revoked_tokens USING btree (user_id, revoked_at DESC);


--
-- Name: idx_user_media_user_created; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_user_media_user_created ON public.user_media USING btree (user_id, created_at DESC);


--
-- Name: idx_vehicle_media_vehicle_created; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_vehicle_media_vehicle_created ON public.vehicle_media USING btree (vehicle_id, created_at DESC);


--
-- Name: idx_vehicles_user_brand_model; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_vehicles_user_brand_model ON public.vehicles USING btree (user_id, brand, model);


--
-- Name: idx_vehicles_user_created; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_vehicles_user_created ON public.vehicles USING btree (user_id, created_at DESC);


--
-- Name: idx_vehicles_user_sort; Type: INDEX; Schema: public; Owner: fueladmin
--

CREATE INDEX idx_vehicles_user_sort ON public.vehicles USING btree (user_id, sort_order, created_at DESC);


--
-- Name: expenses expenses_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: expenses expenses_vehicle_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_vehicle_id_fkey FOREIGN KEY (vehicle_id) REFERENCES public.vehicles(id) ON DELETE CASCADE;


--
-- Name: fillups fillups_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.fillups
    ADD CONSTRAINT fillups_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: fillups fillups_vehicle_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.fillups
    ADD CONSTRAINT fillups_vehicle_id_fkey FOREIGN KEY (vehicle_id) REFERENCES public.vehicles(id) ON DELETE CASCADE;


--
-- Name: revoked_tokens revoked_tokens_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.revoked_tokens
    ADD CONSTRAINT revoked_tokens_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_media user_media_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.user_media
    ADD CONSTRAINT user_media_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_token_revocations user_token_revocations_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.user_token_revocations
    ADD CONSTRAINT user_token_revocations_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: vehicle_media vehicle_media_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.vehicle_media
    ADD CONSTRAINT vehicle_media_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: vehicle_media vehicle_media_vehicle_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.vehicle_media
    ADD CONSTRAINT vehicle_media_vehicle_id_fkey FOREIGN KEY (vehicle_id) REFERENCES public.vehicles(id) ON DELETE CASCADE;


--
-- Name: vehicles vehicles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fueladmin
--

ALTER TABLE ONLY public.vehicles
    ADD CONSTRAINT vehicles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict pvfnUuATwV7kbH6ySWmmq5xqpKW7g180ZFhViuGkSfpHK6ikKKSEhqTlBTWGOKo

