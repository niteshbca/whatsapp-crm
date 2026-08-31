# WhatsApp CRM — Render Deployment Guide (Step by Step)

Aapke project ke 3 parts hain:
| Part | Tech | Render pe kaise |
|------|------|----------------|
| Frontend | Vue 3 + Vite | **Static Site** |
| Backend | Laravel (PHP) | **Web Service** (PHP runtime) |
| WhatsApp service | Node.js (whatsapp-web.js) | Free tier pe **nahi chalegi** (niche dekho) |

> **IMPORTANT:** Render free tier **ephemeral** storage hota hai (restart pe wipe). WhatsApp service ko
> Qt/browser-based session + persistent disk chahiye, isliye usko Render free pe deploy mat karo —
> wo apne laptop par chalao aur `WHATSAPP_SERVICE_URL` me backend ka public URL do.

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
4. Render aapke liye **saari services khud bana dega** (blueprint `backend/render.yaml` se):
   - `whatsapp-crm-db` → PostgreSQL database (free)
   - `whatsapp-crm-backend` → Laravel Web Service
   - `whatsapp-crm-frontend` → Static Site

**Ye farak padta hai:** Blueprint ke liye `render.yaml` **repo ke root me** hona chahiye.
Abhi wo `backend/render.yaml` hai. Isliye Root pe bhi copy banao (deploy se pehle step 2.5).

---

## Step 2.5 — render.yaml ko root me copy karo

Render Blueprint sirf **root directory** ke `render.yaml` ko pack karta hai.
`backend/render.yaml` me `rootDir: backend` already set hai, isliye bas root me copy banao:

```bash
Copy-Item backend\render.yaml .\render.yaml
git add render.yaml
git commit -m "root blueprint"
git push
```

Ab **Blueprint** deploy karo aur Render ko sab kuch khud banane do.

> Agar Blueprint kuch error de, to usko chhodo aur **Manual** tarike se bhi bana sakte ho
> (niche "Manual tarika" section).

---

## Step 3 — Deploy ke baad verify karo

Deploy complete hone ke baad Render tumhe 2 URL dega:

- Backend: `https://whatsapp-crm-backend.onrender.com`
- Frontend: `https://whatsapp-crm-frontend.onrender.com`

**Backend test:**
```
https://whatsapp-crm-backend.onrender.com/up     → "ok" aana chahiye
https://whatsapp-crm-backend.onrender.com/api/me → login message
```

> Note: `VITE_API_URL` abhi `https://whatsapp-crm-backend.onrender.com/api` set hai.
> Agar tumhara backend URL alag ho (jaise `...onrender.com` with suffix), to
> Frontend service ke **Environment** me `VITE_API_URL` update karke **manual deploy** karo.

**Frontend test:** browser me frontend URL kholo → trang login page dikhna chahiye.

---

## Step 4 — WhatsApp login / setup karo

WhatsApp service ko free cloud pe nahi, **apne laptop par** chalao:

```bash
cd C:\Users\LENOVO\Desktop\free\whatsapp
npm install
# .env me LARAVEL_URL ko backend ka public URL do
# LARAVEL_URL=https://whatsapp-crm-backend.onrender.com
node index.js
```

Phir browser me `http://127.0.0.1:3001` kholo aur **QR scan** karo.

---

## Manual tarika (agar Blueprint failed ho)

### Backend (Web Service)
- **Type:** Web Service
- **Root Directory:** `backend`
- **Runtime:** PHP
- **Build Command:** `composer install --no-dev --optimize-autoloader`
- **Start Command:** `bash start.sh`
- **Health Check Path:** `/up`
- **Environment variables:**
  - `APP_ENV=production`
  - `APP_KEY` (generate karke set karo)
  - `DB_CONNECTION=pgsql`
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (Render PostgreSQL se)
  - `SESSION_DRIVER=cookie`
  - `QUEUE_CONNECTION=sync`
  - `CACHE_STORE=database`
  - `PROVIDER_TOKEN=` (koi bhi random secret)
  - `WHATSAPP_SERVICE_URL=http://127.0.0.1:3001`

### Database
Dashboard → New → **PostgreSQL** (free). Phir uske credentials ko backend env me daalo.

### Frontend (Static Site)
- **Type:** Static Site
- **Root Directory:** `frontend`
- **Build Command:** `npm install && npm run build`
- **Publish Directory:** `dist`
- **Environment:** `VITE_API_URL=https://whatsapp-crm-backend.onrender.com/api`

---

## Troubleshooting

- **Health check fail / 502:** `/up` route phle se hai. Baaki logs dekho.
- **Migration na ho:** `migrate --force` start.sh me hai. Logs me check karo.
- **CORS error:** `config/cors.php` me `allowed_origins => ['*']` pehle se hai, theek hai.
- **Data wipe hota hai:** SQLite free me ephemeral hai — isliye **PostgreSQL** use karo.
- **BluePrint ke baad VITE_API_URL galat:** frontend ke Environment me fix karke re-deploy.

---

## Realistic expectation

- **Laravel + Vue + PostgreSQL:** Render free pe **theek chalega**.
- **WhatsApp (whatsapp-web.js):** apne laptop ya **paid** VPS/Railway pe chalao, free cloud nahi.
