# Secure Initial Administrator Setup Guide

To protect HEISVERSE from unauthorized access, there is **no hardcoded default password** and **no public backdoor**.

---

## 🛡️ Step-by-Step Initial Administrator Activation

You have two simple, secure ways to initialize your owner account:

### Option 1: Dedicated One-Time Setup Wizard (Recommended)
1. In your browser, navigate to:
   👉 **[http://localhost:8080/setup-admin.php](http://localhost:8080/setup-admin.php)**
2. Enter your desired administrator **Username**, **Email**, and **Password**.
3. Click **Initialize Administrator Account**.
4. The system creates your account and immediately sets `initial_setup_completed = 1`, permanently locking the script against any future use for maximum security.
5. Log in at **[http://localhost:8080/login.php](http://localhost:8080/login.php)** and access the Admin Control Suite.

---

### Option 2: Standard First-User Registration
1. Navigate to:
   👉 **[http://localhost:8080/register.php](http://localhost:8080/register.php)**
2. Register your account. When the database has 0 users, the backend automatically assigns the `admin` role to the first account. All subsequent accounts will receive the standard `user` role.

### Step 5: Access the Admin Dashboard
Once logged in, click the red **⚙️ Admin** button in the top navigation bar or browse to:
👉 **[http://localhost:8080/admin/index.php](http://localhost:8080/admin/index.php)**

From here, you can:
- Add new movies with multiple authorized streaming sources.
- Moderate users, comments, and reviews.
- Promote or demote team members to administrator.
