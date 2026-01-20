# GitHub CI/CD Setup Guide

This guide explains how to set up automated deployment to cPanel using GitHub Actions.

## Overview

The CI/CD pipeline automatically deploys your code to cPanel whenever you push to the `main` branch. It uses GitHub Secrets to securely store your cPanel credentials.

## Setup Instructions

### Step 1: Configure GitHub Secrets

You need to add your cPanel credentials as secrets in your GitHub repository:

1. Go to your GitHub repository
2. Click on **Settings** tab
3. In the left sidebar, click **Secrets and variables** > **Actions**
4. Click **New repository secret** button
5. Add the following secrets one by one:

| Secret Name | Value | Description |
|------------|-------|-------------|
| `CPANEL_HOSTNAME` | `bh-in-32.webhostbox.net` | Your cPanel server hostname |
| `CPANEL_USERNAME` | `letsdoitadmin` | Your cPanel username |
| `CPANEL_TOKEN` | `P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV` | Your cPanel API token |
| `CPANEL_BASE_PATH` | `/home4/letsdoitadmin/public_html` | Base path on the server |

**Important:** For each secret:
- Click "New repository secret"
- Enter the name exactly as shown above
- Paste the corresponding value
- Click "Add secret"

### Step 2: Verify Workflow File

The workflow file is located at [.github/workflows/deploy.yml](.github/workflows/deploy.yml). It's already configured and will:

- Trigger on every push to `main` branch
- Can also be triggered manually from GitHub Actions tab
- Check out your code
- Set up Node.js environment
- Run the deployment script with your secrets
- Deploy files to your cPanel server

### Step 3: Enable GitHub Actions

1. Go to the **Actions** tab in your GitHub repository
2. If prompted, click "I understand my workflows, go ahead and enable them"

### Step 4: Test the Deployment

You have two options to test:

#### Option A: Automatic Deployment (Push to main)
```bash
git add .
git commit -m "Test CI/CD deployment"
git push origin main
```

#### Option B: Manual Deployment
1. Go to **Actions** tab in GitHub
2. Click on "Deploy to cPanel" workflow
3. Click "Run workflow" button
4. Select `main` branch
5. Click "Run workflow"

### Step 5: Monitor Deployment

1. Go to **Actions** tab
2. Click on the running workflow
3. Watch the deployment progress in real-time
4. Check for any errors in the logs

## Workflow Triggers

The deployment runs automatically when:
- You push commits to the `main` branch
- You manually trigger it from GitHub Actions tab

## What Gets Deployed

The deployment script deploys the following:
- `portal/` directory → `/home4/letsdoitadmin/public_html/portal/`

Files excluded from deployment:
- `node_modules/`
- `.git/`
- `.env` files
- `.DS_Store`
- `Thumbs.db`

## Troubleshooting

### Deployment Fails

1. **Check secrets**: Ensure all four secrets are correctly set in GitHub
2. **Check logs**: Go to Actions tab and view the failed workflow logs
3. **Verify credentials**: Test locally by running `node deploy/cpanel-deploy.js`

### Files Not Uploading

- Check the deployment logs for specific file errors
- Verify file permissions on cPanel
- Ensure the target directories exist on the server

### Authentication Errors

- Verify your cPanel API token is valid
- Check if your IP is allowed in cPanel (if IP restrictions are enabled)
- Ensure the token has necessary permissions

## Local Development

You can still deploy manually from your local machine:

```bash
cd deploy
node cpanel-deploy.js
```

The script will use the hardcoded values when environment variables are not set.

## Security Best Practices

- Never commit credentials to your repository
- Keep your GitHub Secrets up to date
- Regularly rotate your cPanel API tokens
- Review deployment logs for suspicious activity
- Use branch protection rules on `main` branch

## Adding More Directories to Deploy

To deploy additional directories, edit [deploy/cpanel-deploy.js](deploy/cpanel-deploy.js):

```javascript
const deployDirs = [
  { local: '../portal', remote: '/portal' },
  { local: '../google-api', remote: '/google-api' }  // Uncomment or add new entries
];
```

## Need Help?

- Check workflow logs in GitHub Actions tab
- Review [deploy/cpanel-deploy.js](deploy/cpanel-deploy.js) for deployment logic
- Test locally before pushing to ensure everything works

---

## Quick Reference

**Deploy manually:**
```bash
cd deploy
node cpanel-deploy.js
```

**View workflow status:**
- GitHub → Actions tab → Latest workflow run

**Update secrets:**
- GitHub → Settings → Secrets and variables → Actions
