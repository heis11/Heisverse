# Deploying HEISVERSE to Render (Production Guide)

Render is a modern cloud hosting platform. Since Render web services are stateless and do not include a permanent MySQL instance by default, you must use an external managed MySQL database (such as Aiven, PlanetScale, Railway, or Render PostgreSQL with MySQL proxy).

---

## Step 1: Create a Managed MySQL Database

1. Create a free/starter account on a MySQL provider (e.g., [Aiven for MySQL](https://aiven.io) or [Clever Cloud](https://www.clever-cloud.com)).
2. Provision a **MySQL 8** database named `heisverse`.
3. Note your connection credentials:
   - `DB_HOST` (e.g., `mysql-xxxx.aivencloud.com`)
   - `DB_PORT` (e.g., `12345`)
   - `DB_NAME` (`heisverse`)
   - `DB_USER` (e.g., `avnadmin`)
   - `DB_PASS` (`your_strong_remote_password`)
4. Import `database.sql` into your new managed MySQL instance using MySQL Workbench, phpMyAdmin, or the provider's SQL console.

---

## Step 2: Push Your Code to GitHub

1. Initialize git in your project directory:
   ```bash
   git init
   git add .
   git commit -m "HEISVERSE initial production release"
   ```
2. Create a private repository on GitHub named `heisverse`.
3. Push your repository:
   ```bash
   git remote add origin https://github.com/your-username/heisverse.git
   git branch -M main
   git push -u origin main
   ```

---

## Step 3: Create a Web Service on Render

1. Log in to [Render.com](https://render.com).
2. Click **New + > Web Service**.
3. Connect your GitHub repository `heisverse`.
4. Configure the service:
   - **Name**: `heisverse`
   - **Region**: Choose the region closest to you or your database.
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Dockerfile Path**: `./Dockerfile`
   - **Health Check Path**: `/api.php?action=health`

---

## Step 4: Configure Environment Variables on Render

In the **Environment Variables** tab on Render, add:

| Key | Value |
| :--- | :--- |
| `APP_ENV` | `production` |
| `APP_NAME` | `HEISVERSE` |
| `APP_SECRET` | *(Generate a 64-char random hex string)* |
| `DB_HOST` | *(Your remote managed MySQL host)* |
| `DB_PORT` | *(Your remote MySQL port, e.g. 3306)* |
| `DB_NAME` | `heisverse` |
| `DB_USER` | *(Your remote MySQL username)* |
| `DB_PASS` | *(Your remote MySQL password)* |
| `TMDB_API_KEY` | *(Your TMDB API key if available)* |
| `YOUTUBE_API_KEY` | *(Your YouTube API key if available)* |
| `AI_API_KEY` | *(Your Google Gemini or AI key if available)* |

Click **Create Web Service**. Render will build your Docker container, verify the `/api.php?action=health` endpoint, and assign an SSL-secured `https://heisverse.onrender.com` URL!
