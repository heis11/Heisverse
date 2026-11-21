# Windows Docker Desktop Installation & User Guide

This guide walks you step-by-step through setting up Docker Desktop on Windows.

---

## Step 1: Install Docker Desktop

1. You already have `Docker Desktop Installer.exe` downloaded in your `C:\Users\User\Downloads` folder.
2. Double-click `Docker Desktop Installer.exe` to run the installer.
3. When prompted, ensure **Use WSL 2 instead of Hyper-V (recommended)** is checked.
4. Follow the setup prompts and click **Close and restart** if Windows requires a reboot.

---

## Step 2: Start Docker Desktop

1. Click the Windows Start menu and type **Docker Desktop**.
2. Click to open it.
3. Wait until the status indicator in the bottom-left of the Docker Desktop window turns green (**Engine running**).

---

## Step 3: Open Project in Antigravity

1. In Google Antigravity, click **File > Open Folder**.
2. Select your HEISVERSE project folder:
   `C:\Users\User\.gemini\antigravity\scratch\heisverse`
3. Click **Select Folder**.

---

## Step 4: Run HEISVERSE Containers

Open the terminal in Antigravity or Windows PowerShell and run:

```powershell
docker compose up -d --build
```

**What this command does**:
- `--build`: Packages your PHP Apache code into a custom container with PDO MySQL and security settings.
- `-d` (detached mode): Runs the containers smoothly in the background so your terminal remains free.

---

## Step 5: Verify Containers are Healthy

Run:
```powershell
docker compose ps
```
You should see:
- `heisverse_db`: Status `Up (healthy)`, port `3306->3306`
- `heisverse_app`: Status `Up`, port `8080->80`

Open your browser to:
👉 **[http://localhost:8080](http://localhost:8080)**

---

## Step 6: Shutting Down Safely

When you finish your work:
```powershell
docker compose down
```
> [!IMPORTANT]
> `docker compose down` safely stops the containers while preserving all your movies, reviews, and user accounts inside the persistent volume. Never run `docker compose down -v` unless you explicitly want to wipe your database!
