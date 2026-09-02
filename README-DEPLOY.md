# WhatsApp CRM — Render Deployment Guide (Step by Step)

Aapke project ke 3 parts hain:
| Part | Tech | Render pe kaise |
|------|------|----------------|
| Frontend | Vue 3 + Vite | **Static Site** (`type: web` + `runtime: static`) |
| Backend | Laravel (PHP 8.4) | **Web Service** (`runtime: docker`, Dockerfile) |
| WhatsApp service | Node.js (whatsapp-web.js) | **Web Service** (`runtime: docker`, Dockerfile + Chromium) |

> **NOTE:** WhatsApp service Render free pe deploy ho sakti hai, lekin free tier **ephemeral** storage
> hai — restart (ya 15 min idle) pe session wipe ho jata hai, isliye QR dobara scan karna padta hai.
> Free tier WhatsApp-web.js ko WhatsApp ban bhi sakta hai (unofficial API). Production ke liye paid
> instance ya persistent disk recommend karte hain.

---

## Step 1 — Pehle GitHub par push karo

Render GitHub se auto-deploy karta hai.

```bash
cd C:\Users\LENOVO\Desktop\free
git init
git add .
git commit -m "init"
```

Phir GitHub par ek **private repo** banao aur:
```bash
git remote add origin https://github.com/YOUR_USERNAME/whatsapp-crm.git
git branch -M main
git push -u origin main
```

> `.gitignore` me `.env`, sqlite, node_modules, `.wwebjs_auth` sab already excluded hain — koi secret leak nahi hoga.

---

## Step 2 — Render par account banao aur Blueprint deploy karo

1. https://render.com par account banao (GitHub se signup).
2. **Dashboard → New → Blueprint** par click karo.
3. Apna GitHub repo connect karo.
4. Render aapke liye **saari services khud bana dega** (root `render.yaml` se):
   - `whatsapp-crm-db` → PostgreSQL database (free)
   - `whatsapp-crm-backend` → Laravel Web Service (docker)
   - `whatsapp-crm-frontend` → Static Site
   - `whatsapp-crm-whatsapp` → Node WhatsApp Web Service (docker)

**Ye farak padta hai:** Blueprint ke liye `render.yaml` **repo ke root me** hona chahiye.
Abhi wo root me hai (`./render.yaml`). Deploy ke baad Render tumhe har service ka public URL dega.

---

## Step 2.5 — render.yaml root me hai (confirm)

Render Blueprint sirf **root directory** ke `render.yaml` ko pack karta hai.
Root `render.yaml` me sab services defined hain (`rootDir` sahi set hai). Bas push karo aur sync karo.

```bash
git add -A
git commit -m "update render blueprint"
git push
```

Render par **Blueprint service** kholo → **Sync** (ya Manual Deploy) karo.

> Blueprint sync ke time Render `WHATSAPP_SERVICE_URL` (backend pe) ka value puch sakta hai —
> shaant raho, pehle sync karke whatsapp service ka public URL dekh lo, phir `WHATSAAPP_SERVICE_URL`
> me wo URL daal ke backend re-deploy karo (niche Step 4.5).

---

## Step 3 — Deploy ke baad verify karo

Deploy complete hone ke baad Render tumhe URLs dega:

- Backend: `https://whatsapp-crm-backend-g4xx.onrender.com`
- Frontend: `https://whatsapp-crm-frontend-w400.onrender.com`
- WhatsApp: `https://whatsapp-crm-whatsapp-XXXX.onrender.com`

**Backend test:**
```
https://whatsapp-crm-backend-g4xx.onrender.com/up     → "ok" aana chahiye
https://whatsapp-crm-backend-g4xx.onrender.com/api/me → login message
```

> Note: `VITE_API_URL` (frontend) `https://whatsapp-crm-backend-g4xx.onrender.com/api` set hai.
> Agar tumhara backend URL alag ho (jaise `...onrender.com` with suffix), to
> Frontend service ke **Environment** me `VITE_API_URL` update karke **manual deploy** karo.

**Frontend test:** browser me frontend URL kholo → login page dikhna chahiye.

## Step 4 — WhatsApp service ko link karo

Blueprint sync ke baad:
1. WhatsApp service ka **public URL** note karo (Dashboard me service kholo).
2. Backend service → **Environment** → `WHATSAPP_SERVICE_URL` me wo URL daalo:
   ```
   https://whatsapp-crm-whatsapp-XXXX.onrender.com
   ```
3. Backend ko **Manual Deploy** karo.
4. Ab frontend me jao → WhatsApp tab → **Connect** → QR scan karo.

## Step 4.5 — WhatsApp login / setup

WhatsApp service khud Render pe chalki hogi (`whatsapp-crm-whatsapp`).
Browser me frontend me jao → WhatsApp connect karo → QR code scan karo.

> Free tier par session ephemeral hai (restart pe wipe). Production ke liye paid instance
> + persistent disk use karo. `whatsapp/Dockerfile` me `SESSION_DIR=/data/sessions` set hai —
> Render pe disk attach karne par session persist rahegi.

---

## Manual tarika (agar Blueprint failed ho)

### Backend (Web Service)
- **Type:** Web Service
- **Root Directory:** `backend`
- **Runtime:** Docker
- **Dockerfile Path:** `./Dockerfile`
- **Health Check Path:** `/up`
- **Environment variables:**
  - `APP_ENV=production`
  - `APP_KEY` (generate karke set karo)
  - `DB_CONNECTION=pgsql`
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (Render PostgreSQL se)
  - `SESSION_DRIVER=database`
  - `QUEUE_CONNECTION=sync`
  - `CACHE_STORE=database`
  - `PROVIDER_TOKEN=` (koi bhi random secret — **backend aur whatsapp service DONO me same**)
  - `WHATSAPP_SERVICE_URL=https://whatsapp-crm-whatsapp-XXXX.onrender.com`

### WhatsApp service (Web Service)
- **Type:** Web Service
- **Root Directory:** `whatsapp`
- **Runtime:** Docker
- **Dockerfile Path:** `./Dockerfile`
- **Health Check Path:** `/health`
- **Environment variables:**
  - `LARAVEL_URL=https://whatsapp-crm-backend-g4xx.onrender.com`
  - `PROVIDER_TOKEN=` (backend wala SAME token)
  - `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true`
  - `PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium`

### Database
Dashboard → New → **PostgreSQL** (free). Phir uske credentials ko backend env me daalo.

### Frontend (Static Site)
- **Type:** Static Site
- **Root Directory:** `frontend`
- **Build Command:** `npm install && npm run build`
- **Publish Directory:** `dist`
- **Environment:** `VITE_API_URL=https://whatsapp-crm-backend-g4xx.onrender.com/api`

---

## Troubleshooting

- **Health check fail / 502:** `/up` route phle se hai. Baaki logs dekho.
- **Migration na ho:** `migrate --force` start.sh me hai. Logs me check karo.
- **CORS error:** `config/cors.php` me allowed origins list hai — frontend ka **exact** origin
  (`https://whatsapp-crm-frontend-w400.onrender.com`) usme hona chahiye.
- **Data wipe hota hai:** SQLite free me ephemeral hai — isliye **PostgreSQL** use karo.
- **BluePrint ke baad VITE_API_URL galat:** frontend ke Environment me fix karke re-deploy.
- **WhatsApp "Failed to connect to 127.0.0.1:3001":** backend ka `WHATSAPP_SERVICE_URL` abhi
  localhost par hai. Usse WhatsApp service ke public Render URL par set karke backend redeploy karo.
- **WhatsApp QR dobara aata hai:** free tier ephemeral storage — session restart pe wipe.
  Paid instance + disk, ya laptop par service chalao.

---

## Realistic expectation

- **Laravel + Vue + PostgreSQL:** Render free pe **theek chalega**.
- **WhatsApp (whatsapp-web.js):** Render free pe chal jayegi par session ephemeral hai
  (restart pe wipe). Production ke liye laptop, ya paid VPS/Railway, ya persistent disk.
