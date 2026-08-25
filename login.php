<?php
require_once 'config.php';

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

// Handle login action
if ($action === 'login') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (!isValidEmail($email)) {
        $response['message'] = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $response['message'] = 'Please enter your password.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $response['message'] = 'No account found with this email.';
            } elseif (!$user['is_active']) {
                $response['message'] = 'Your account has been deactivated. Please contact an administrator.';
            } elseif (!verifyPassword($password, $user['password_hash'])) {
                $response['message'] = 'Incorrect password. Please try again.';
            } else {
                // Login successful - remove sensitive data from session
                unset($user['password_hash']);
                setSessionUser($user);
                
                // Set remember me cookie if requested
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('ncc_remember', $token, time() + (86400 * 7), '/', '', false, true);
                    // Note: In production, store token in a remember_tokens table
                }
                
                $response['success'] = true;
                $response['message'] = 'Login successful.';
                $response['user'] = $user;
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error. Please try again later.';
        }
    }
}

// Handle registration action
elseif ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($name)) {
        $response['message'] = 'Please enter your full name.';
    } elseif (!isValidEmail($email)) {
        $response['message'] = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $response['message'] = 'Passwords do not match.';
    } elseif (!$terms) {
        $response['message'] = 'You must agree to the terms of use.';
    } else {
        try {
            $pdo = getDB();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $response['message'] = 'An account with this email already exists. Please login instead.';
            } else {
                // Create new user
                $hashedPassword = hashPassword($password);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$name, $email, $hashedPassword]);
                
                $response['success'] = true;
                $response['message'] = 'Account created successfully.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error. Please try again later.';
        }
    }
}

// Handle logout action
elseif ($action === 'logout') {
    clearSession();
    if (isset($_COOKIE['ncc_remember'])) {
        setcookie('ncc_remember', '', time() - 3600, '/');
    }
    $response['success'] = true;
    $response['message'] = 'Logged out successfully.';
}

// Check if user is already logged in
if (isLoggedIn() && $action !== 'logout') {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NCC Zimbabwe — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,450;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F6F3EA; --paper-raised:#FFFFFF; --ink:#1C231F; --ink-soft:#5B655D;
    --forest:#1F4D3A; --forest-deep:#153327; --brass:#AD8A4D; --sage:#E4EBE1;
    --hairline:#DCD5C2; --rule:1px solid var(--hairline); --danger:#A23B2E;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; background:var(--paper); color:var(--ink);
    font-family:'IBM Plex Sans',sans-serif; -webkit-font-smoothing:antialiased;
    display:flex; align-items:center; justify-content:center; padding:32px 20px;
  }
  .serif{font-family:'Fraunces',serif;}
  .mono{font-family:'IBM Plex Mono',monospace;}

  .shell{width:100%; max-width:920px; display:grid; grid-template-columns:1fr 1fr; border:var(--rule); background:var(--paper-raised); border-radius:14px; overflow:hidden; box-shadow:0 24px 60px -30px rgba(21,51,39,0.25);}

  /* left brand panel */
  .brand{
    background:
      radial-gradient(circle at 30% 20%, rgba(255,255,255,0.06), transparent 55%),
      linear-gradient(160deg, var(--forest-deep), var(--forest) 80%);
    color:#F3EFE2; padding:44px 38px; display:flex; flex-direction:column; justify-content:space-between; position:relative;
  }
  .brand::after{
    content:""; position:absolute; inset:16px; border:1px dashed rgba(243,239,226,0.28); border-radius:8px; pointer-events:none;
  }
  .brand-eyebrow{text-transform:uppercase; letter-spacing:.14em; font-size:11px; color:var(--brass); font-weight:600; margin:0 0 14px;}
  .brand-title{font-family:'Fraunces',serif; font-size:28px; font-weight:600; line-height:1.15; margin:0 0 14px;}
  .brand-sub{font-size:13.5px; color:rgba(243,239,226,0.78); line-height:1.6; max-width:280px;}
  .seal-mini{
    width:64px; height:64px; border-radius:50%; border:1.5px solid rgba(243,239,226,0.55);
    display:flex; align-items:center; justify-content:center; font-family:'Fraunces',serif; font-size:22px; font-weight:600;
    margin-bottom:22px;
  }
  .brand-foot{font-size:11.5px; color:rgba(243,239,226,0.55);}

  /* right form panel */
  .form-panel{padding:44px 40px;}
  .auth-tabs{display:flex; gap:0; margin-bottom:30px; border:var(--rule); border-radius:9px; padding:4px; background:var(--paper);}
  .auth-tab{
    flex:1; text-align:center; padding:10px; font-size:13px; font-weight:600; color:var(--ink-soft);
    border-radius:6px; cursor:pointer; border:none; background:transparent; font-family:'IBM Plex Sans',sans-serif;
  }
  .auth-tab.active{background:var(--forest); color:#fff;}

  .view{display:none;}
  .view.active{display:block;}
  h2.form-title{font-family:'Fraunces',serif; font-size:22px; color:var(--forest-deep); margin:0 0 6px;}
  .form-desc{font-size:13px; color:var(--ink-soft); margin:0 0 24px; line-height:1.5;}

  .field{margin-bottom:16px;}
  .field label{display:block; font-size:11.5px; font-weight:600; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;}
  .field input{
    width:100%; border:var(--rule); border-radius:8px; padding:11px 13px; font-size:14px;
    font-family:'IBM Plex Sans',sans-serif; background:var(--paper); color:var(--ink);
  }
  .field input:focus{outline:2px solid var(--forest); outline-offset:1px;}
  .field-error{color:var(--danger); font-size:12px; margin-top:5px; display:none;}
  .field.has-error input{border-color:var(--danger);}
  .field.has-error .field-error{display:block;}

  .pw-row{position:relative;}
  .pw-toggle{
    position:absolute; right:11px; top:50%; transform:translateY(-50%); background:none; border:none;
    color:var(--ink-soft); font-size:12px; cursor:pointer; font-family:'IBM Plex Sans',sans-serif; font-weight:500;
  }

  .row-inline{display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;}
  .checkbox-row{display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--ink-soft);}
  .checkbox-row input{accent-color:var(--forest);}
  .link-btn{background:none; border:none; color:var(--forest); font-size:12.5px; font-weight:600; cursor:pointer; padding:0; font-family:'IBM Plex Sans',sans-serif;}
  .link-btn:hover{text-decoration:underline;}

  .btn-submit{
    width:100%; background:var(--forest); color:#fff; border:none; padding:13px; border-radius:8px;
    font-size:14px; font-weight:600; cursor:pointer; font-family:'IBM Plex Sans',sans-serif;
  }
  .btn-submit:hover{background:var(--forest-deep);}
  .btn-submit:disabled{opacity:.6; cursor:not-allowed;}

  .switch-line{text-align:center; font-size:12.5px; color:var(--ink-soft); margin-top:20px;}
  .switch-line button{background:none; border:none; color:var(--forest); font-weight:600; cursor:pointer; font-size:12.5px; font-family:'IBM Plex Sans',sans-serif;}

  .strength-bar{height:4px; border-radius:3px; background:var(--hairline); margin-top:8px; overflow:hidden;}
  .strength-fill{height:100%; width:0%; background:var(--danger); transition:width .2s, background .2s;}
  .strength-label{font-size:11px; color:var(--ink-soft); margin-top:4px;}

  .banner{
    display:none; padding:11px 14px; border-radius:8px; font-size:12.5px; margin-bottom:18px; align-items:center; gap:8px;
  }
  .banner.show{display:flex;}
  .banner.error{background:#F3E0DC; color:var(--danger);}
  .banner.success{background:var(--sage); color:var(--forest-deep);}

  .success-panel{text-align:center; padding:20px 0;}
  .success-icon{
    width:56px; height:56px; border-radius:50%; background:var(--sage); color:var(--forest-deep);
    display:flex; align-items:center; justify-content:center; font-size:26px; margin:0 auto 18px;
  }
  .success-panel h2{font-family:'Fraunces',serif; color:var(--forest-deep); margin:0 0 8px; font-size:20px;}
  .success-panel p{color:var(--ink-soft); font-size:13.5px; margin:0 0 22px; line-height:1.5;}

  .note-security{font-size:11px; color:var(--ink-soft); margin-top:22px; padding-top:18px; border-top:var(--rule); line-height:1.5;}

  @media (max-width:760px){
    .shell{grid-template-columns:1fr;}
    .brand{padding:32px 28px;}
    .brand::after{display:none;}
    .form-panel{padding:32px 26px;}
  }
</style>
</head>
<body>

<div class="shell">
  <div class="brand">
    <div>
      <div class="seal-mini">NCC</div>
      <p class="brand-eyebrow">National Competitiveness Commission</p>
      <h1 class="brand-title">Complaints &amp; Compliments Register</h1>
      <p class="brand-sub">Sign in to log, track, and respond to stakeholder feedback — all in one private register.</p>
    </div>
    <p class="brand-foot">Zimbabwe · FY2026</p>
  </div>

  <div class="form-panel">

    <div id="mainAuth">
      <div class="auth-tabs">
        <button class="auth-tab active" id="tabLogin">Log In</button>
        <button class="auth-tab" id="tabRegister">Register</button>
      </div>

      <div class="banner <?= !empty($response['message']) ? ($response['success'] ? 'show success' : 'show error') : '' ?>" id="banner"><?= htmlspecialchars($response['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>

      <!-- LOGIN VIEW -->
      <div class="view active" id="viewLogin">
        <h2 class="form-title">Welcome back</h2>
        <p class="form-desc">Log in to continue to your register.</p>

        <form id="loginForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <input type="hidden" name="action" value="login">
          <div class="field" id="loginEmailField">
            <label>Email</label>
            <input type="email" id="loginEmail" name="email" placeholder="you@ncc.org.zw" autocomplete="username" required>
            <p class="field-error">Enter a valid email address</p>
          </div>
          <div class="field" id="loginPwField">
            <label>Password</label>
            <div class="pw-row">
              <input type="password" id="loginPassword" name="password" placeholder="••••••••" autocomplete="current-password" required>
              <button type="button" class="pw-toggle" data-target="loginPassword">Show</button>
            </div>
            <p class="field-error">Enter your password</p>
          </div>

          <div class="row-inline">
            <label class="checkbox-row"><input type="checkbox" name="remember" id="loginRemember"> Remember me</label>
            <button type="button" class="link-btn" id="forgotBtn">Forgot password?</button>
          </div>

          <button type="submit" class="btn-submit" id="loginSubmit">Log In</button>
        </form>

        <p class="switch-line">Don't have an account? <button id="toRegister">Register here</button></p>
      </div>

      <!-- REGISTER VIEW -->
      <div class="view" id="viewRegister">
        <h2 class="form-title">Create an account</h2>
        <p class="form-desc">Set up access to your private register.</p>

        <form id="registerForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <input type="hidden" name="action" value="register">
          <div class="field" id="regNameField">
            <label>Full Name</label>
            <input type="text" id="regName" name="name" placeholder="e.g. Tendai Moyo" autocomplete="name" required>
            <p class="field-error">Enter your full name</p>
          </div>
          <div class="field" id="regEmailField">
            <label>Email</label>
            <input type="email" id="regEmail" name="email" placeholder="you@ncc.org.zw" autocomplete="username" required>
            <p class="field-error">Enter a valid email address</p>
          </div>
          <div class="field" id="regPwField">
            <label>Password</label>
            <div class="pw-row">
              <input type="password" id="regPassword" name="password" placeholder="At least 8 characters" autocomplete="new-password" required>
              <button type="button" class="pw-toggle" data-target="regPassword">Show</button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <p class="strength-label" id="strengthLabel">&nbsp;</p>
            <p class="field-error">Password must be at least 8 characters</p>
          </div>
          <div class="field" id="regConfirmField">
            <label>Confirm Password</label>
            <div class="pw-row">
              <input type="password" id="regConfirm" name="confirm" placeholder="Re-enter password" autocomplete="new-password" required>
              <button type="button" class="pw-toggle" data-target="regConfirm">Show</button>
            </div>
            <p class="field-error">Passwords do not match</p>
          </div>

          <div class="row-inline" style="margin-bottom:20px;">
            <label class="checkbox-row"><input type="checkbox" name="terms" id="regTerms" value="1"> I agree to the terms of use</label>
          </div>

          <button type="submit" class="btn-submit" id="registerSubmit">Create Account</button>
        </form>

        <p class="switch-line">Already have an account? <button id="toLogin">Log in</button></p>
      </div>

      <p class="note-security">Your account is securely stored in our database. This is a protected system for NCC staff only.</p>
    </div>

  </div>
</div>

<script>
// Password show/hide
document.querySelectorAll('.pw-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    const isPw = input.type === 'password';
    input.type = isPw ? 'text' : 'password';
    btn.textContent = isPw ? 'Hide' : 'Show';
  });
});

// Password strength indicator
document.getElementById('regPassword').addEventListener('input', function(e) {
  const v = this.value;
  let score = 0;
  if (v.length >= 8) score++;
  if (v.length >= 12) score++;
  if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const pct = Math.min(100, (score / 5) * 100);
  const fill = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  fill.style.width = pct + '%';
  if (v.length === 0) { fill.style.width = '0%'; label.textContent = '\u00a0'; return; }
  if (score <= 1) { fill.style.background = '#A23B2E'; label.textContent = 'Weak'; }
  else if (score <= 3) { fill.style.background = '#AD8A4D'; label.textContent = 'Fair'; }
  else { fill.style.background = '#1F4D3A'; label.textContent = 'Strong'; }
});

// Tab switching
document.getElementById('tabLogin').addEventListener('click', () => {
  document.getElementById('tabLogin').classList.add('active');
  document.getElementById('tabRegister').classList.remove('active');
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.getElementById('viewLogin').classList.add('active');
  document.getElementById('banner').className = 'banner';
});
document.getElementById('tabRegister').addEventListener('click', () => {
  document.getElementById('tabRegister').classList.add('active');
  document.getElementById('tabLogin').classList.remove('active');
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.getElementById('viewRegister').classList.add('active');
  document.getElementById('banner').className = 'banner';
});
document.getElementById('toRegister').addEventListener('click', () => document.getElementById('tabRegister').click());
document.getElementById('toLogin').addEventListener('click', () => document.getElementById('tabLogin').click());

// Forgot password handler
document.getElementById('forgotBtn').addEventListener('click', () => {
  const b = document.getElementById('banner');
  b.textContent = "Password reset functionality is coming soon. Please contact your administrator for assistance.";
  b.className = 'banner show error';
});

// Handle login form submission
document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = this;
  const fd = new FormData(form);
  const banner = document.getElementById('banner');
  const btn = document.getElementById('loginSubmit');
  btn.disabled = true; btn.textContent = 'Signing in…';

  fetch(window.location.href, {
    method: 'POST',
    body: fd
  })
  .then(res => {
    const wasRedirected = res.redirected && new URL(res.url).pathname.endsWith('/dashboard.php');
    return res.text().then(html => ({ html, wasRedirected }));
  })
  .then(({ html, wasRedirected }) => {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const newBanner = tmp.querySelector('#banner');
    if (newBanner) {
      banner.className = newBanner.className;
      banner.textContent = newBanner.textContent;
    }
    // Check if login was successful by looking for dashboard redirect or success message
    if (wasRedirected || html.includes('dashboard.php') || html.includes('window.location.href')) {
      window.location.href = 'dashboard.php';
    } else {
      btn.disabled = false; btn.textContent = 'Log In';
    }
  })
  .catch(() => {
    banner.className = 'banner show error';
    banner.textContent = 'An error occurred. Please try again.';
    btn.disabled = false; btn.textContent = 'Log In';
  });
});

// Handle register form submission
document.getElementById('registerForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = this;
  const fd = new FormData(form);
  const banner = document.getElementById('banner');
  const btn = document.getElementById('registerSubmit');
  btn.disabled = true; btn.textContent = 'Creating account…';

  fetch(window.location.href, {
    method: 'POST',
    body: fd
  })
  .then(res => {
    const wasRedirected = res.redirected && new URL(res.url).pathname.endsWith('/dashboard.php');
    return res.text().then(html => ({ html, wasRedirected }));
  })
  .then(({ html, wasRedirected }) => {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const newBanner = tmp.querySelector('#banner');
    if (newBanner) {
      banner.className = newBanner.className;
      banner.textContent = newBanner.textContent;
    }
    if (newBanner && newBanner.classList.contains('success')) {
      setTimeout(() => window.location.href = 'login.php', 1200);
    } else if (wasRedirected || html.includes('dashboard.php') || html.includes('window.location.href')) {
      window.location.href = 'dashboard.php';
    } else {
      btn.disabled = false; btn.textContent = 'Create Account';
    }
  })
  .catch(() => {
    banner.className = 'banner show error';
    banner.textContent = 'An error occurred. Please try again.';
    btn.disabled = false; btn.textContent = 'Create Account';
  });
});

<?php if (!empty($response['message']) && !$response['success']): ?>
// Show error message from server
(function() {
  const b = document.getElementById('banner');
  b.textContent = <?= json_encode($response['message']) ?>;
  b.className = 'banner show error';
})();
<?php endif; ?>

</script>
</body>
</html>