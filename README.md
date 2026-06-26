# Personal Blog (Decap CMS & Vercel)

A fully serverless, Git-backed personal blog built with PHP and Decap CMS (formerly Netlify CMS). This project is specifically designed to run on Vercel without needing any database or persistent storage environment.

## 🚀 Architecture Overview
This blog operates as a **Git-based CMS**:
- **No Database**: All content is stored directly in the GitHub repository as Markdown files inside the `content/posts/` directory.
- **Vercel Native**: Because Vercel's functions are read-only, we use Decap CMS to authenticate via GitHub and push commits directly to your repository!
- **Auto Deployments**: When you create or edit a post via the `/admin` panel, the GitHub API commit instantly triggers a new Vercel deployment, seamlessly rebuilding your live site.

---

## 💻 Local Development Setup

To run this project locally, you only need PHP installed on your machine.

1. **Clone the repository:**
   ```bash
   git clone <your-repo-url>
   cd personal-blog
   ```

2. **Start the PHP built-in server (specifying the router file):**
   ```bash
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
4. Click **Deploy**. Vercel will automatically read `vercel.json`, download the `vercel-php` runtime, and deploy your site.

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
Decap CMS cannot securely store your Client Secret in the browser. You must deploy a tiny microservice to handle the OAuth handshake.
1. The easiest way is to deploy a pre-built Vercel OAuth provider. 
2. Use this popular open-source template: [Decap CMS GitHub OAuth Provider on Vercel](https://github.com/ublabs/netlify-cms-oauth).
3. Deploy it to your Vercel account, and supply it with your **GitHub Client ID** and **GitHub Client Secret** as Environment Variables.
4. Once deployed, update your `admin/config.yml` one last time by adding the `base_url` parameter to point to your new OAuth provider:
   ```yaml
   backend:
     name: github
     repo: your-username/your-repo-name
     base_url: https://your-new-oauth-provider-url.vercel.app
   ```
5. Commit and push.

---

## 📝 Writing Content
Once deployed, simply navigate to `https://your-vercel-deployment-url.vercel.app/admin`. 
- Click **Login with GitHub**.
- You will be taken to a beautiful, visual WYSIWYG editor where you can write Markdown posts.
- **Images**: Since Vercel is serverless, local image uploads are disabled. When inserting an image, simply paste an external URL (from Imgur, AWS, Cloudinary, etc.) into the "Featured Image URL" field or directly into the Markdown body.
