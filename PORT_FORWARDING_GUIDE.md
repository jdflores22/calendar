# Port Forwarding Guide

## What is Port Forwarding?

Port forwarding allows you to access services running on your local machine (like your Symfony development server) from other devices on your network or even remotely.

## Common Use Cases

- Access your local development server from a mobile device for testing
- Share your work-in-progress with team members
- Test your application on different devices
- Debug mobile-specific issues

## Methods for Port Forwarding

### 1. Using Symfony CLI (Recommended for Symfony Projects)

The Symfony CLI has built-in support for exposing your local server:

```bash
# Start your Symfony server with expose
symfony server:start --port=8000 --allow-http

# In another terminal, expose it
symfony server:expose
```

This creates a public URL that tunnels to your local server.

### 2. Using ngrok (Most Popular)

**Installation:**
- Download from https://ngrok.com/download
- Extract and add to your PATH
- Sign up for a free account and get your auth token

**Usage:**
```bash
# Authenticate (one-time setup)
ngrok config add-authtoken YOUR_AUTH_TOKEN

# Forward your local port (e.g., 8000)
ngrok http 8000

# For HTTPS
ngrok http https://localhost:8000
```

You'll get a public URL like: `https://abc123.ngrok.io`

### 3. Using Kiro/VS Code Port Forwarding

**Note:** This feature requires additional setup in Kiro. If you encounter errors about missing `code-tunnel.exe`, use ngrok or LocalTunnel instead (see methods 2 and 4).

If properly configured:
1. Open the **Ports** panel (View → Ports)
2. Click **Forward a Port**
3. Enter your port number (e.g., 8000)
4. Right-click the port → **Port Visibility** → **Public**
5. Copy the forwarded address

### 4. Using LocalTunnel

**Installation:**
```bash
npm install -g localtunnel
```

**Usage:**
```bash
# Forward port 8000
lt --port 8000

# With custom subdomain
lt --port 8000 --subdomain myapp
```

### 5. Using SSH Tunnel (For Remote Servers)

If you need to access a service on a remote server:

```bash
# Forward remote port 8000 to local port 8000
ssh -L 8000:localhost:8000 user@remote-server

# Reverse tunnel (expose local to remote)
ssh -R 8000:localhost:8000 user@remote-server
```

## For Your Symfony Application

### Quick Start with ngrok:

1. **Start your Symfony server:**
   ```bash
   symfony server:start
   ```
   Note the port (usually 8000)

2. **In a new terminal, start ngrok:**
   ```bash
   ngrok http 8000
   ```

3. **Copy the forwarding URL** (e.g., `https://abc123.ngrok-free.app`)

4. **Update your .env.local if needed:**
   ```env
   APP_URL=https://abc123.ngrok-free.app
   ```

### Security Considerations

- **Never expose sensitive data** through public tunnels
- Use authentication on your routes
- Consider IP whitelisting for sensitive endpoints
- Disable debug mode in production-like environments
- Use HTTPS tunnels when possible

### Troubleshooting

**Issue: "Invalid Host header"**
- Add the forwarded domain to your trusted hosts
- In `config/packages/framework.yaml`:
  ```yaml
  framework:
      trusted_hosts: ['^localhost$', '^127\.0\.0\.1$', '.*\.ngrok-free\.app$']
  ```

**Issue: Session/Cookie problems**
- Update `config/packages/framework.yaml`:
  ```yaml
  framework:
      session:
          cookie_secure: auto
          cookie_samesite: lax
  ```

**Issue: CSRF token errors**
- Ensure your APP_URL matches the forwarded URL
- Clear cache: `php bin/console cache:clear`

## Recommended Workflow

1. Start Symfony server: `symfony server:start`
2. Start ngrok: `ngrok http 8000`
3. Test locally first: `http://localhost:8000`
4. Share ngrok URL for external access
5. Monitor ngrok dashboard: `http://localhost:4040`

## Free vs Paid Options

**Free:**
- ngrok (limited sessions, random URLs)
- LocalTunnel (can be unstable)
- VS Code forwarding (requires GitHub account)

**Paid:**
- ngrok Pro (custom domains, more sessions)
- Cloudflare Tunnel (free tier available)
- Tailscale (for team access)

## Next Steps

Choose the method that fits your needs:
- **Quick testing?** → Use ngrok
- **Team collaboration?** → Use VS Code forwarding or Tailscale
- **Production-like testing?** → Use Cloudflare Tunnel
- **Mobile testing?** → Use ngrok or LocalTunnel
