# Personal Blog (Decap CMS & Vercel)

A fully serverless, Git-backed personal blog built with PHP and Decap CMS (formerly Netlify CMS). This project is specifically designed to run on Vercel without needing any persistent storage environment for the posts, while using Supabase to power the native comment system.

## 🚀 Architecture Overview
This blog operates as a **Git-based CMS**:
- **No Database for Posts**: All content is stored directly in the GitHub repository as Markdown files inside the `content/posts/` directory.
- **Supabase for Comments**: A PostgreSQL database on Supabase is used to store and retrieve user comments via REST API.
- **Vercel Native**: Because Vercel's functions are read-only, we use Decap CMS to authenticate via GitHub and push commits directly to your repository!
- **Auto Deployments**: When you create or edit a post via the `/admin` panel, the GitHub API commit instantly triggers a new Vercel deployment, seamlessly rebuilding your live site.

---

## 🗄️ Database Setup (Supabase for Comments)

To enable the comment system, you need a free [Supabase](https://supabase.com/) project.

1. Create a new project in Supabase.
2. Go to the **SQL Editor** and run the following query to create the `comments` table:

```sql
CREATE TABLE comments (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    post_slug TEXT NOT NULL,
    author_name TEXT NOT NULL,
    author_email TEXT NOT NULL,
    content TEXT NOT NULL,
    approved BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

3. Go to **Project Settings > API** and copy your **Project URL** and **anon public key**.
4. You will need to use these as Environment Variables (see local setup and deployment steps).

---

## 💻 Local Development Setup

To run this project locally, you only need PHP installed on your machine.

1. **Clone the repository:**
   ```bash
   git clone <your-repo-url>
   cd personal-blog
   ```

2. **Start the PHP built-in server (specifying the router file & env vars):**
   ```bash
   SUPABASE_URL="https://your-project.supabase.co" \
   SUPABASE_KEY="your-anon-public-key" \
   php -S 127.0.0.1:8000 -t . api/index.php
   ```

3. **View the site:**
   - Frontend: `http://127.0.0.1:8000/`
   - Admin Panel: `http://127.0.0.1:8000/admin`
   *(Note: To test the admin panel locally, you must use the `local_backend` configuration in Decap CMS, or test it in production).*

---

## 🚀 Step-by-Step Deployment Guide

Follow these exact steps to get your blog and admin panel live on Vercel.

### Step 1: Prepare the Configuration
Before pushing to GitHub, you must update the Decap CMS configuration to point to your specific repository.

1. Open `admin/config.yml`.
2. Locate the `repo:` field at the top.
3. Change `your-username/your-repo-name` to your actual GitHub repository (e.g., `pepe/personal-blog`).
4. Commit and push this change to GitHub.

### Step 2: Deploy to Vercel
1. Log into [Vercel](https://vercel.com) and click **"Add New Project"**.
2. Import your GitHub repository.
3. **Important**: Leave the Build Command and Output Directory fields **empty** (or default).
4. **Environment Variables**: Add your Supabase credentials:
   - `SUPABASE_URL`: Your Supabase Project URL
   - `SUPABASE_KEY`: Your Supabase anon public key
5. Click **Deploy**. Vercel will automatically read `vercel.json`, download the `vercel-php` runtime, and deploy your site.

### Step 3: Setup GitHub OAuth Authentication
Because you are deploying on Vercel (not Netlify), Decap CMS requires a way to authenticate with GitHub securely to commit files. You need to set up an OAuth application.

1. Go to your GitHub account settings: **Settings > Developer settings > OAuth Apps**.
2. Click **New OAuth App**.
3. Fill in the details:
   - **Application name**: My Blog CMS
   - **Homepage URL**: `https://your-vercel-deployment-url.vercel.app`
   - **Authorization callback URL**: `https://api.netlify.com/auth/done` *(If using a free external proxy, or use your own deployed OAuth server's callback).*
4. Generate a **Client ID** and **Client Secret**.

### Step 4: Deploy a Vercel OAuth Provider (Required)
Decap CMS is a frontend-only application, meaning it runs entirely in your browser. Because of this, it cannot securely hold your GitHub "Client Secret" (which acts like a password). To solve this, you need a tiny "middleman" server to securely pass the login request to GitHub.

Don't worry—you don't have to code this! You can clone and deploy a free, pre-built one in 2 minutes:

1. Click this exact link to instantly deploy the open-source middleman to your Vercel account: 
   👉 **[Deploy Netlify CMS OAuth Provider to Vercel](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fublabs%2Fnetlify-cms-oauth)**
2. Vercel will ask you to create a new Git repository for this project. Name it something like `my-blog-oauth` and click **Create**.
3. Vercel will then ask you to configure **Environment Variables**. You must provide two values (you generated these back in Step 3):
   - `OAUTH_GITHUB_CLIENT_ID`: (Paste your GitHub Client ID here)
   - `OAUTH_GITHUB_CLIENT_SECRET`: (Paste your GitHub Client Secret here)
4. Click **Deploy**.
5. Once the deployment finishes, Vercel will give you a new public URL for this project (e.g., `https://my-blog-oauth.vercel.app`). **Copy this URL.**
6. **Important:** Go back to your GitHub OAuth App from Step 3, and update the **Authorization callback URL** to be exactly: `https://my-blog-oauth.vercel.app/callback` (replace with your actual Vercel URL and make sure to add `/callback` at the end).
7. Finally, go to your blog repository and open `admin/config.yml`. Add the `base_url` parameter pointing to your new Vercel OAuth URL:
   ```yaml
   backend:
     name: github
     repo: your-username/your-repo-name
     base_url: https://my-blog-oauth.vercel.app
   ```
8. Commit and push this change. You can now log into your admin panel in production!

---

## 📝 Writing & Previewing Content
Once deployed, simply navigate to `https://your-vercel-deployment-url.vercel.app/admin`. 
- Click **Login with GitHub**.
- You will be taken to a visual editor where you can write Markdown posts.
- **Drafts**: You can save posts with the draft option turned on (`draft: true`).
  - Public visitors at `/` will **only** see published posts (`draft: false`).
  - You can view all posts (including drafts) by visiting **`/draft`**. Draft posts will be clearly labeled with a `BORRADOR` badge and a warning banner will appear.
- **Images**: When inserting an image, paste an external URL (from Imgur, AWS, Cloudinary, etc.) into the "Featured Image URL" field or directly into the Markdown body.
