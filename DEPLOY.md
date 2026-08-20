# Deploying to Render (free tier)

## One-time setup

1. **Init git and push to GitHub** (Render deploys from a git repo):
   ```bash
   git init
   git add .
   git commit -m "Initial commit for Render deploy"
   ```
   Create a repo on GitHub, then:
   ```bash
   git remote add origin <your-repo-url>
   git push -u origin main
   ```

2. **Create the Render service:**
   - Go to https://dashboard.render.com → New → Blueprint
   - Connect your GitHub repo — Render will detect `render.yaml` automatically
   - It builds from `Dockerfile` on the free plan

3. **Set the Firebase secret** (never committed to git):
   - In the Render dashboard, open the service → Environment
   - Add env var `FIREBASE_SERVICE_ACCOUNT_JSON`
   - Paste the **entire contents** of your local `config/firebase-service-account.json` as the value (it's one JSON blob, Render handles multi-line env vars fine)
   - The container writes it to `config/firebase-service-account.json` on startup (see `docker-entrypoint.sh`)

4. **Deploy** — Render builds and starts the service. First request after idle will be slow (free tier sleeps after ~15 min inactivity, cold start ~30-60s).

## Notes

- `data/db.json` (JSON fallback store) resets on every deploy/restart — Render's free tier has an ephemeral filesystem. This is fine since Firestore is the live data store; the JSON file is just the local fallback and isn't relied on in production.
- `data/.firestore-token-cache.json` also resets on restart — harmless, it's just re-fetched.
- If you ever rotate the Firebase service account key, update the `FIREBASE_SERVICE_ACCOUNT_JSON` env var in Render and redeploy (or just restart the service).
- To test the Docker build locally first:
  ```bash
  docker build -t piko-taz-bowling .
  docker run -p 8080:80 -e FIREBASE_SERVICE_ACCOUNT_JSON="$(cat config/firebase-service-account.json)" piko-taz-bowling
  ```
  Then visit http://localhost:8080
