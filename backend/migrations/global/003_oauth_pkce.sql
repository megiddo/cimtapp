-- PKCE verifier for Google authorization-code start/callback.
-- Additive for databases created from 001 before code_verifier existed.
ALTER TABLE oauth_states ADD COLUMN code_verifier TEXT;
