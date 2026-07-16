// Passkey (WebAuthn) registration + sign-in. Talks to api/passkey_*.php,
// which do all the actual cryptographic verification server-side via
// web-auth/webauthn-lib — this file only handles browser<->JSON plumbing.
(function () {
  function b64urlToBuffer(b64url) {
    var pad = '='.repeat((4 - (b64url.length % 4)) % 4);
    var b64 = (b64url + pad).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(b64);
    var buf = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) buf[i] = raw.charCodeAt(i);
    return buf.buffer;
  }

  function bufferToB64url(buf) {
    var bytes = new Uint8Array(buf);
    var str = '';
    for (var i = 0; i < bytes.byteLength; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function supported() {
    return !!(window.PublicKeyCredential && navigator.credentials);
  }

  async function registerPasskey(label) {
    var optsRes = await fetch('/api/passkey_register_options.php');
    var opts = await optsRes.json();
    if (opts.error) throw new Error(opts.error);

    opts.challenge = b64urlToBuffer(opts.challenge);
    opts.user.id = b64urlToBuffer(opts.user.id);
    if (opts.excludeCredentials) {
      opts.excludeCredentials.forEach(function (c) { c.id = b64urlToBuffer(c.id); });
    }

    var cred = await navigator.credentials.create({ publicKey: opts });
    var payload = {
      id: cred.id,
      rawId: bufferToB64url(cred.rawId),
      type: cred.type,
      response: {
        clientDataJSON: bufferToB64url(cred.response.clientDataJSON),
        attestationObject: bufferToB64url(cred.response.attestationObject),
      },
    };

    var res = await fetch('/api/passkey_register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ credential: payload, label: label, csrf_token: window.CSRF_TOKEN }),
    });
    var data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'Registration failed.');
    return data;
  }

  async function loginWithPasskey(remember) {
    var optsRes = await fetch('/api/passkey_auth_options.php');
    var opts = await optsRes.json();
    if (opts.error) throw new Error(opts.error);

    opts.challenge = b64urlToBuffer(opts.challenge);
    if (opts.allowCredentials) {
      opts.allowCredentials.forEach(function (c) { c.id = b64urlToBuffer(c.id); });
    }

    var assertion = await navigator.credentials.get({ publicKey: opts });
    var payload = {
      id: assertion.id,
      rawId: bufferToB64url(assertion.rawId),
      type: assertion.type,
      response: {
        clientDataJSON: bufferToB64url(assertion.response.clientDataJSON),
        authenticatorData: bufferToB64url(assertion.response.authenticatorData),
        signature: bufferToB64url(assertion.response.signature),
        userHandle: assertion.response.userHandle ? bufferToB64url(assertion.response.userHandle) : null,
      },
    };

    var csrfInput = document.querySelector('input[name="csrf_token"]');
    var res = await fetch('/api/passkey_auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        credential: payload,
        remember: !!remember,
        csrf_token: window.CSRF_TOKEN || (csrfInput ? csrfInput.value : ''),
      }),
    });
    var data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'Sign-in failed.');
    return data;
  }

  window.Passkey = { supported: supported, registerPasskey: registerPasskey, loginWithPasskey: loginWithPasskey };

  document.addEventListener('click', function (e) {
    var loginBtn = e.target.closest('[data-passkey-login]');
    if (loginBtn) {
      var statusEl = document.querySelector('[data-passkey-status]');
      if (!supported()) {
        if (statusEl) statusEl.textContent = 'Passkeys are not supported in this browser.';
        return;
      }
      var rememberBox = document.querySelector('input[name="remember"]');
      loginBtn.disabled = true;
      if (statusEl) statusEl.textContent = 'Waiting for your passkey…';
      loginWithPasskey(rememberBox && rememberBox.checked)
        .then(function (data) { window.location = data.redirect || '/'; })
        .catch(function (err) {
          if (statusEl) statusEl.textContent = err.message;
          loginBtn.disabled = false;
        });
      return;
    }

    var addBtn = e.target.closest('[data-passkey-add]');
    if (addBtn) {
      var addStatus = document.querySelector('[data-passkey-add-status]');
      if (!supported()) {
        if (addStatus) addStatus.textContent = 'Passkeys are not supported in this browser.';
        return;
      }
      var labelInput = document.querySelector('[data-passkey-label]');
      var label = labelInput ? labelInput.value.trim() || 'Passkey' : 'Passkey';
      addBtn.disabled = true;
      if (addStatus) addStatus.textContent = 'Follow your browser/device prompt…';
      registerPasskey(label)
        .then(function () { window.location.reload(); })
        .catch(function (err) {
          if (addStatus) addStatus.textContent = err.message;
          addBtn.disabled = false;
        });
    }
  });
})();
