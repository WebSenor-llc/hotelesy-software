# Hotelesy — Deploy to DigitalOcean App Platform

A step-by-step playbook. Follow each section in order. Total time: ~30–45 min if you have an account, ~60 min if you're starting fresh.

---

## What you're building

- **Web service** (PHP 8.3 + Nginx) running Hotelesy → public URL like `hotelesy-xyz.ondigitalocean.app`
- **Worker** (queue listener for emails, e-invoice generation, etc.)
- **Managed MySQL 8** database
- **Pre-deploy job** that auto-runs migrations on every release
- **Auto-deploy on git push** to your `main` branch

**Cost:** ~$32/mo for the smallest paid tier (web $5 + worker $5 + MySQL $15 + extras). The basic web instance can be the **free** tier if you're just testing.

---

## Step 0 — One-time accounts

You need three things. Skip any you already have.

1. **GitHub account** — sign up at https://github.com if you don't have one (free).
2. **DigitalOcean account** — sign up at https://www.digitalocean.com (needs a credit card; usually $200 free credit for new sign-ups).
3. **Git installed on your Mac** — open Terminal and run:
   ```bash
   git --version
   ```
   If it says "command not found", run `xcode-select --install` and follow the macOS prompt.

---

## Step 1 — Push the code to GitHub

### 1a. Create the repo on GitHub

1. Go to https://github.com/new
2. **Owner:** your username
3. **Repository name:** `hotelesy` (or anything)
4. **Visibility:** Private (recommended)
5. **Do NOT** check any "Initialize this repository with…" boxes
6. Click **Create repository**
7. On the next page, copy the URL like `https://github.com/YOUR-USERNAME/hotelesy.git`

### 1b. Push your local code

Open Terminal and paste these commands one at a time. Replace `YOUR-USERNAME/hotelesy` with what GitHub shows you:

```bash
cd /Users/shubhamsoni/Sites/hotelesy

# Make sure git is set up (only first time on this Mac)
git config --global user.name "Piyush Sharma"
git config --global user.email "piyush@websenor.com"

# Initialize git in the project (if not already)
git init -b main

# Stage everything (gitignore excludes .env etc.)
git add .

# First commit
git commit -m "Initial Hotelesy commit"

# Connect to GitHub
git remote add origin https://github.com/YOUR-USERNAME/hotelesy.git

# Push it
git push -u origin main
```

GitHub will ask you to authenticate. Two options:

**Option A — Personal access token (easiest):**
1. Go to https://github.com/settings/tokens
2. Generate new token (classic) → Expiration: 90 days → Check **repo** scope → Generate
3. Copy the token (starts with `ghp_...`)
4. When git asks for password, paste the token

**Option B — GitHub Desktop:** install https://desktop.github.com and use the GUI to publish the repo.

After it succeeds, refresh GitHub — you should see all the project files there.

---

## Step 2 — Generate an APP_KEY

This is the encryption key Laravel uses for sessions and cookies. **Do not reuse the local one.**

In your Terminal, in the project folder:

```bash
cd /Users/shubhamsoni/Sites/hotelesy
php artisan key:generate --show
```

It prints something like `base64:abcDEF1234.../=`. **Copy this whole string** including the `base64:` prefix — you'll paste it into DigitalOcean in the next step.

---

## Step 3 — Create the App on DigitalOcean

1. Sign into https://cloud.digitalocean.com
2. Top-right → **Create** → **Apps**
3. **Service Provider:** GitHub → **Connect** if first time → authorize "DigitalOcean App Platform" for your `hotelesy` repo
4. Pick the **hotelesy** repo, branch **main**, leave **Autodeploy** ticked → Next
5. DO will scan the repo and detect Laravel — you'll see a draft "web" service
6. **IMPORTANT:** click **Edit Plan** at the top and switch the **Resource Type** from default to use our spec file:
   - Click the gear icon next to the web service
   - **Source:** Dockerfile / Buildpack — choose **Buildpack** (PHP)
   - Or, easier: scroll down to **App Spec** at the bottom of the create page → click **Edit App Spec** → paste the content of `.do/app.yaml` from your repo (DO supports loading the spec from `.do/app.yaml` automatically; if it doesn't, paste manually)
7. Add a **Database** resource:
   - Click **Add Resource** → **Database** → **MySQL** → smallest plan (~$15/mo) → name it `hotelesy-db`
8. **Environment Variables** — DO will show the env vars from app.yaml. Set the ones marked SECRET:
   - `APP_KEY` → paste the base64:... key from Step 2
   - `APP_URL` → leave blank for now; you'll set it after deploy
   - `GITHUB_REPO` → `YOUR-USERNAME/hotelesy` (only used inside spec; safe to set to the same value)
9. **Region:** Bangalore (BLR) for India users. Else use the nearest.
10. **App Name:** `hotelesy`
11. Click **Create Resources** at the bottom

DO now provisions the database (~5 min) and builds your app (~10 min). Watch the build log on the dashboard.

---

## Step 4 — Run the production seeder (first deploy only)

The migrate job runs automatically. But you also need to seed the initial owner user once.

After the first successful deploy, in DO dashboard:

1. Open your app → **Console** tab → choose the **web** service
2. A web terminal opens. Paste:
   ```bash
   php artisan db:seed --class=ProductionSeeder --force
   ```
3. You should see: `Login: admin@hotelesy.app / changeme`

---

## Step 5 — Visit the site and log in

1. On the app's overview page, copy the **Live App URL** (e.g. `https://hotelesy-abcde.ondigitalocean.app`)
2. Open it in Chrome
3. Log in with:
   - Email: `admin@hotelesy.app`
   - Password: `changeme`
4. **Immediately** go to Setup → Users → click your user → **Edit** → change the password

---

## Step 6 — Set APP_URL correctly

Now that you know the live URL, set it as an env var so cookies and Sanctum work:

1. Dashboard → your app → **Settings** → **App-Level Environment Variables**
2. Find `APP_URL` → set value to your live URL (e.g. `https://hotelesy-abcde.ondigitalocean.app`)
3. Set `SANCTUM_STATEFUL_DOMAINS` to the same hostname (without `https://`)
4. Save → DO triggers a redeploy. Wait ~5 min.

---

## Step 7 — Custom domain (optional)

If you want `hotelesy.websenor.com` instead of the random `.ondigitalocean.app` URL:

1. App dashboard → **Settings** → **Domains** → **Add Domain**
2. Enter `hotelesy.websenor.com`
3. DO shows a CNAME record. In your DNS provider (where websenor.com is registered):
   - Add CNAME `hotelesy` → `hotelesy-abcde.ondigitalocean.app`
4. Wait 5–30 min for DNS to propagate. DO auto-provisions Let's Encrypt SSL.
5. Update `APP_URL` env var to `https://hotelesy.websenor.com` and redeploy.

---

## Step 8 — Set up email (required for FolioInvoice / confirmation emails)

The default config logs emails. To actually send, pick a provider:

### Easiest: Mailgun (10k emails/mo free for 30 days, then $35/mo)
1. Sign up at https://mailgun.com
2. Add and verify your sending domain (DNS records)
3. Get the API key from dashboard
4. In DO app dashboard, set env vars:
   - `MAIL_MAILER=mailgun`
   - `MAILGUN_DOMAIN=mg.yourdomain.com`
   - `MAILGUN_SECRET=key-xxxxxxxxx` (mark as SECRET)

### Cheapest: Amazon SES
- $0.10 per 1000 emails
- Verify domain → request production access (24h approval)
- Use SMTP credentials with `MAIL_MAILER=smtp`, `MAIL_HOST=email-smtp.ap-south-1.amazonaws.com`

After saving env vars DO redeploys automatically.

---

## Step 9 — Switch file uploads to DigitalOcean Spaces (when you start uploading photos / IDs)

App Platform's filesystem is **ephemeral** — files uploaded to `/storage/app/public/` get wiped on every deploy. For room photos and ID proofs you need Spaces (S3-compatible, $5/mo for 250 GB).

1. DO dashboard → **Spaces** → Create a Space → name it `hotelesy-uploads` → Bangalore region
2. **Spaces Keys** → generate access key — copy the secret immediately
3. In your app's env vars, set:
   - `FILESYSTEM_DISK=s3`
   - `AWS_ACCESS_KEY_ID=<spaces key>`
   - `AWS_SECRET_ACCESS_KEY=<spaces secret>` (SECRET)
   - `AWS_DEFAULT_REGION=blr1`
   - `AWS_BUCKET=hotelesy-uploads`
   - `AWS_ENDPOINT=https://blr1.digitaloceanspaces.com`
   - `AWS_USE_PATH_STYLE_ENDPOINT=false`
4. Redeploy.

You may need a small migration on the upload code paths to use the `s3` disk explicitly. Tell me when you're at this step and I'll patch it.

---

## Step 10 — Deploy updates from now on

Just push to git:

```bash
cd /Users/shubhamsoni/Sites/hotelesy
git add .
git commit -m "your change message"
git push
```

DO sees the push, runs the migrate job, builds the new container, swaps traffic. Total ~3 min.

---

## Common gotchas and fixes

**"Build failed: composer install"** — usually a `php: command not found` issue or a missing Composer extension. Check the build log; if PHP version mismatch, set `"php": "^8.3"` is what we use.

**500 error on first visit** — almost always a missing env var. Check Settings → Environment Variables and confirm `APP_KEY` is set.

**"Database refused connection"** — the database resource name in app.yaml must match. Look at Settings → Resources → confirm `hotelesy-db` exists.

**Migrations didn't run** — check the **Activity** tab. If the pre-deploy job failed, click into it for the error log. Most common: column already exists → fix migration with `Schema::hasColumn` guard.

**Login redirect loop** — `APP_URL` is wrong or `SANCTUM_STATEFUL_DOMAINS` doesn't include your domain. Match them exactly to your live URL.

**File uploads disappear after deploy** — you didn't switch to Spaces yet (Step 9).

---

## Backups

Managed MySQL on DO has automatic daily backups for 7 days, included. To verify:
- App → Resources → hotelesy-db → **Backups** tab
- You can restore to a point-in-time from there.

For a manual backup any time:
```bash
# In DO web console for the web service
php artisan db:dump > /tmp/backup.sql && cat /tmp/backup.sql
```

(Copy the output to your local machine.)

---

## Support / what to ask me

If anything errors during these steps, copy the error message and paste it back to me. I'll diagnose. Most common failures are predictable and have one-line fixes.

For the Spaces migration in Step 9, ping me when you're there — there's a small Storage facade swap I'll wire in.
