# Backup and AMK rotation

PepTrack encrypts each user sqlite with a per-user DEK, then wraps that DEK with the application master key (AMK) `CIMT_MASTER_KEY`. A backup that omits the AMK cannot be restored.

## Backup unit

Copy these together. All three are required:

1. `data/global.sqlite` — users, sessions, wrapped DEKs, peptide catalog, rate-limit counters
2. `data/users/*.enc` — encrypted per-user stores (compounds, uses, syringes)
3. **`CIMT_MASTER_KEY`** — the AMK that unwraps every `users.encrypted_dek`

Do not copy `data/tmp/` (plaintext while a request is in flight). Docker mounts that directory as tmpfs. Dev and prod Compose both bind the host `./data` directory, so backups are a copy of that folder plus the AMK.

## Restore

1. Stop the app.
2. Copy `global.sqlite` and `users/*.enc` back into `DATA_DIR`.
3. Set `CIMT_MASTER_KEY` to the **same** AMK used when those files were written.
4. Start the app.

If the AMK does not match, user files will not decrypt (HTTP 500 with a generic error). **AMK loss means every `users/*.enc` file is unreadable.** There is no recovery key in v1.

## Decrypt-to-sqlite export (logged-in user)

`GET /api/v1/me/export` (cookie session) returns `application/octet-stream` — the caller’s **decrypted** sqlite (`peptrack-export.sqlite`) after pending schema mutations. Settings also exposes **Download sqlite** for the signed-in user. This is the **exfil unit**: it includes the account snapshot, syringes, compounds, and uses.

The handler decrypts under the user lock, streams the bytes, and shreds the temp file before the response finishes. Plaintext is not left on disk after the response. Do not commit or copy that download into `DATA_DIR`.

## AMK rotation

DEK wrapping can rotate without rewriting `.enc` files. File ciphertext stays bound to the DEK; only `users.encrypted_dek` and `dek_nonce` change.

```bash
# Current AMK is CIMT_MASTER_KEY. Pass the new 64-hex (or 32-byte base64) key.
docker compose run --rm --no-deps app php bin/rotate-amk.php <new-master-key>
```

Or from `backend/`: `php bin/rotate-amk.php <new-master-key>`.

After a successful run:

1. Set `CIMT_MASTER_KEY` to the **new** key.
2. Restart the app.
3. Keep the old key in the backup set until you confirm logins still open stores.

The CLI unwraps each DEK with the old AMK, wraps it with the new one, then confirms a user store still decrypts (`SQLite format 3` header) without rewriting `.enc` files. If you skip updating env, the next request will fail to unwrap DEKs.
