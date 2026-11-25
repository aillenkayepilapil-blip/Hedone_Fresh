/* =====================================================================
   Hedone Fresh – home page script (forms, OTP, log-in, password reset)
   The browser checks are only for convenience. The server (PHP) repeats
   EVERY check, because browser checks can be bypassed.
   ===================================================================== */
(function () {
  'use strict';

  const H = window.Hedone;
  const $ = H.$;
  const REQ = 'This is a required field.';

  let loggedIn = false;
  let registeredValidUntil = '';

  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const DAYS = Array.from({ length: 31 }, (_, i) => i + 1);
  const YEARS = Array.from({ length: 100 }, (_, i) => new Date().getFullYear() - i);
  const SCHOOLS = ['Caraga State University', 'Father Saturnino Urios University', 'Surigao del Norte State University'];
  const ROMAN = ['I', 'II', 'III', 'IV', 'IX', 'X'];


  /* ===================================================================
     1. HEADER LINKS AND POP-UPS
     =================================================================== */
  const LINKS = {
    home:     ['Home', 'Register', 'Log-in'],
    login:    ['Home', 'Register'],
    register: ['Home', 'Log-in'],
    loggedin: ['Home', 'Log-out']
  };
  const ACTIONS = { 'Home': 'home', 'Register': 'register', 'Log-in': 'login', 'Log-out': 'logout' };

  function setState(state) {
    $('#hdrlinks').innerHTML = LINKS[state]
      .map(label => `<a href="#" data-act="${ACTIONS[label]}">${label}</a>`)
      .join('');
  }
  const baseState = () => (loggedIn ? 'loggedin' : 'home');

  function closeAll() {
    document.querySelectorAll('.popup').forEach(popup => { popup.hidden = true; });
    $('#backdrop').hidden = true;
  }

  // name = which pop-up; state = which header links to show (the "page" the user is on)
  function openPopup(name, state) {
    closeAll();
    const popup = $('#p-' + name);
    popup.style.top = ($('.topwrap').getBoundingClientRect().bottom + 8) + 'px';
    popup.hidden = false;
    $('#backdrop').hidden = false;
    setState(state || name);

    if (name === 'register') { setValidity(); loadCaptcha('r'); }
    if (name === 'login') loadCaptcha('l');
    if (name === 'forgot') { $('#fp1').hidden = false; $('#fp2').hidden = true; loadCaptcha('f'); }

    const firstInput = popup.querySelector('input');
    if (firstInput) firstInput.focus();
  }

  document.addEventListener('click', event => {
    const link = event.target.closest('[data-act]');
    if (!link) return;
    event.preventDefault();
    if (link.classList.contains('disabled')) return;

    const action = link.dataset.act;

    if (action === 'home') {
      closeAll();
      setState(baseState());
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else if (action === 'logout') {
      logout(false);
    } else if (!loggedIn) {
      if (action === 'register' || action === 'login') openPopup(action);
      if (action === 'forgot') openPopup('forgot', 'login');
    }
  });

  $('#backdrop').addEventListener('click', () => { closeAll(); setState(baseState()); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { closeAll(); setState(baseState()); }
  });

  // Show / Hide buttons next to password boxes
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-eye]');
    if (!button) return;

    const box = $('#' + button.dataset.eye);
    const showing = box.type === 'password';
    box.type = showing ? 'text' : 'password';
    button.textContent = showing ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', String(showing));
    button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
  });


  /* ===================================================================
     2. FORM BUILDERS
     =================================================================== */
  const field = (id, label, inner, optional, extraClass) =>
    `<div class="field ${extraClass || ''}">` +
      `<label for="${id}">${label}` +
        (optional ? ' <span class="opt">optional</span>' : ' <span class="req">*</span>') +
      '</label>' + inner +
      `<p class="err" id="e-${id}" role="alert"></p>` +
    '</div>';

  const input = (id, type, placeholder, extra) =>
    `<input id="${id}" name="${id}" type="${type || 'text'}" placeholder="${placeholder || ''}" autocomplete="off" ${extra || ''}>`;

  const options = (list, firstLabel) =>
    `<option value="">${firstLabel}</option>` + list.map(item => `<option>${item}</option>`).join('');

  // Password box with its own Show/Hide button
  const passwordBox = (id, withMeter) =>
    '<div class="pwwrap">' + input(id, 'password', '', 'maxlength="64"') +
    `<button type="button" class="eye" data-eye="${id}" aria-label="Show password" aria-pressed="false">Show</button></div>` +
    (withMeter ? `<div class="meter"><i id="${id}-bar"></i></div>` : '');

  // The CAPTCHA picture is made by php/captcha.php; prefix r = register, l = log-in, f = forgot password
  const captchaBox = prefix =>
    '<div class="field">' +
      `<label for="${prefix}cap">Type the characters you see <span class="req">*</span></label>` +
      '<div class="cap-row">' +
        `<img id="${prefix}img" class="cap-img" alt="CAPTCHA picture" width="220" height="60">` +
        `<button type="button" class="cap-refresh" data-cap="${prefix}" aria-label="Show a new picture">↻</button>` +
      '</div>' +
      `<input id="${prefix}cap" type="text" maxlength="5" autocomplete="off">` +
      `<p class="err" id="e-${prefix}cap" role="alert"></p>` +
    '</div>';

  function loadCaptcha(prefix) {
    $('#' + prefix + 'img').src = 'php/captcha.php?' + Date.now();
    $('#' + prefix + 'cap').value = '';
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-cap]');
    if (button) loadCaptcha(button.dataset.cap);
  });

  const birthdateRow =
    '<div class="field full"><label>Birthdate <span class="req">*</span></label><div class="row3">' +
      `<div><label class="sub" for="bm">Month</label><select id="bm" name="bm">${options(MONTHS, 'Select')}</select></div>` +
      `<div><label class="sub" for="bd">Day</label><select id="bd" name="bd">${options(DAYS, 'Select')}</select></div>` +
      `<div><label class="sub" for="by">Year</label><select id="by" name="by">${options(YEARS, 'Select')}</select></div>` +
      '<div><label class="sub" for="age">Age</label><input id="age" readonly placeholder="Auto"></div>' +
    '</div><p class="err" id="e-bd" role="alert"></p></div>';

  const QUESTION_BANK = [
    'What was the name of your first pet?',
    'Who was your best friend in elementary school?',
    'Who was your favorite teacher in high school?',
    'What is your mother’s maiden first name?',
    'What street did you live on in Grade 1?',
    'What was the first song you learned by heart?',
    'What was the name of your elementary school?',
    'What dish did you cook for the first time?'
  ];

  const securityQuestions = [1, 2, 3].map(n => {
    const choices = QUESTION_BANK.map((question, index) => `<option value="${index + 1}">${question}</option>`).join('');
    return field('qn' + n, 'Security Question ' + n,
        `<select id="qn${n}" name="qn${n}"><option value="">Choose a question</option>${choices}</select>`, 0, 'full') +
      field('q' + n, 'Your Answer', input('q' + n), 0, 'full');
  }).join('');

  const closeButton = '<button type="button" class="xbtn" data-act="home" aria-label="Close">×</button>';


  /* ===================================================================
     3. THE FORMS (HTML)
     =================================================================== */
  $('#reg').innerHTML =
    closeButton +
    '<h3>Student details</h3><div class="grid">' +
      field('rid', 'ID Number', input('rid', 'text', 'xxxx-xxxx', 'maxlength="9"'), 0, 'full') +
      field('fn', 'First Name', input('fn', 'text', 'Jennifer', 'maxlength="50"')) +
      field('mn', 'Middle Name', input('mn', 'text', 'Anne', 'maxlength="50"'), 1) +
      field('ln', 'Family Name', input('ln', 'text', 'Woods', 'maxlength="50"')) +
      field('ext', 'Name Extension',
        `<select id="ext" name="ext">${options(['Jr.', 'Sr.', 'Other'], 'None')}</select>` +
        '<input id="ext_other" name="ext_other" placeholder="I, II, III, IV, IX or X" maxlength="3" hidden>', 1) +
      birthdateRow +
      field('em', 'Email', input('em', 'email', 'name@email.com', 'maxlength="100"'), 0, 'full') +
      field('un', 'Username', input('un', 'text', 'juan2026', 'maxlength="20"'), 0, 'full') +
      field('pw', 'Password', passwordBox('pw', true)) +
      field('pw2', 'Re-enter Password', input('pw2', 'password', '', 'maxlength="64"')) +
    '</div>' +
    '<h3>Address</h3><div class="grid">' +
      field('st', 'Purok/Street', input('st', 'text', 'Purok 5'), 0, 'full') +
      field('br', 'Barangay', input('br')) +
      field('ct', 'Municipality/City', input('ct', 'text', 'Davao City')) +
      field('pv', 'Province', input('pv', 'text', 'Davao Del Sur')) +
      field('co', 'Country', input('co', 'text', 'Philippines')) +
      field('zp', 'ZIP Code', input('zp', 'text', '8000', 'maxlength="4"')) +
    '</div>' +
    '<h3>Membership details</h3><div class="grid">' +
      field('cn', 'Contact Number', input('cn', 'text', '09xxxxxxxxx', 'maxlength="11" inputmode="numeric"')) +
      field('sc', 'School', `<select id="sc" name="sc">${options(SCHOOLS, 'Select school')}</select>`) +
      field('pr', 'Course/Program', input('pr', 'text', 'Information Technology')) +
    '</div>' +
    '<h3>Security questions</h3><p class="hint">Choose 3 different questions and remember your answers.</p>' +
    securityQuestions +
    captchaBox('r') +
    '<div class="field"><label class="check">' +
      '<input type="checkbox" id="consent" name="consent" value="1"> ' +
      '<span>I have read the <a href="privacy-notice.html" target="_blank" rel="noopener">Privacy Notice</a> and agree to the collection and processing of my personal data in accordance with the Data Privacy Act of 2012 (RA 10173).</span>' +
    '</label><p class="err" id="e-consent" role="alert"></p></div>' +
    '<p class="validity" id="validity"></p>' +
    '<div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>' +   // honeypot
    '<button class="btn submit" type="submit">Create account</button><p class="err" id="e-form" role="alert"></p>';

  $('#login').innerHTML =
    closeButton +
    field('lu', 'Username', input('lu')) +
    field('lp', 'Password', passwordBox('lp')) +
    captchaBox('l') +
    '<p class="err" id="e-lock" role="alert"></p>' +
    '<button class="btn submit" type="submit" id="lbtn">Log in</button>' +
    '<p class="alt"><a href="#" id="forgot" data-act="forgot" hidden>Forgot Password? Reset Here</a></p>' +
    '<p class="alt">No account yet? <a href="#" id="reglink" data-act="register">Please register here</a></p>';

  $('#otp').innerHTML =
    closeButton +
    '<p class="hint" id="otp-msg"></p>' +
    field('otpcode', 'Verification code', input('otpcode', 'text', '123456', 'maxlength="6" inputmode="numeric"')) +
    '<button class="btn submit" type="submit" id="otp-btn">Verify</button>' +
    '<p class="alt"><button type="button" class="link-btn" id="resend">Resend code</button> <span id="resend-note"></span></p>';

  $('#fp1').innerHTML =
    closeButton +
    field('fu', 'Username', input('fu')) +
    captchaBox('f') +
    '<button class="btn submit" type="submit">Continue</button><p class="err" id="e-fform" role="alert"></p>';

  $('#fp2').innerHTML =
    closeButton +
    '<p class="hint">Choose one of your questions and answer it.</p><div id="fq"></div>' +
    field('fa', 'Your Answer', input('fa')) +
    field('fa2', 'Re-enter Your Answer', input('fa2')) +
    '<button class="btn submit" type="submit">Verify answer</button><p class="err" id="e-fform2" role="alert"></p>';

  $('#change').innerHTML =
    closeButton +
    field('cp1', 'Enter Password', passwordBox('cp1', true)) +
    field('cp2', 'Re-enter Password', input('cp2', 'password', '', 'maxlength="64"')) +
    '<button class="btn submit" type="submit">Change Password</button><p class="err" id="e-cform" role="alert"></p>';


  /* ===================================================================
     4. VALIDATION RULES (each returns '' when the value is fine)
     =================================================================== */
  const nameCheck = (id, label, optional) => () => {
    const v = $('#' + id).value.trim();
    if (!v) return optional ? '' : REQ;
    if (/ {2}/.test(v)) return 'Double space is not allowed.';
    if (/\d/.test(v)) return `Numbers are not allowed in ${label}.`;
    if (/[^A-Za-z ]/.test(v)) return `Special characters are not allowed in ${label}.`;
    if (v.length > 1 && v === v.toUpperCase()) return 'Do not use all capital letters.';
    if (/(.)\1\1/i.test(v)) return 'Three consecutive identical letters are not allowed.';
    if (!v.split(' ').every(word => /^[A-Z][a-z]*$/.test(word))) return 'First letter of your name must be capital.';
    if (v.length < 2 || v.length > 50) return `${label} must be 2–50 characters.`;
    return '';
  };

  const addressCheck = (id, label, min, max) => () => {
    const v = $('#' + id).value, trimmed = v.trim();
    if (!trimmed) return REQ;
    if (/ {2}/.test(v)) return 'Double space is not allowed.';
    if (trimmed.length < min || trimmed.length > max) return `${label} must be ${min}–${max} characters.`;
    if (!/^[A-Za-z ]+$/.test(v) || !trimmed.split(' ').every(word => /^[A-Z][a-z]*$/.test(word))) {
      return `${label} must use letters and spaces only, in Title Case.`;
    }
    return '';
  };

  const specialCount = v => (v.match(/[^A-Za-z0-9\s]/g) || []).length;
  const strengthOf = v => (v.length >= 13 && specialCount(v) >= 3 ? 3 : v.length >= 12 && specialCount(v) >= 2 ? 2 : 1);
  const weakMessage = 'Password is too weak. Use at least 12 characters with 2 special characters.';

  const ageFromForm = () => {
    const year = +$('#by').value, month = MONTHS.indexOf($('#bm').value), day = +$('#bd').value;
    if (!year || month < 0 || !day) return null;
    const birth = new Date(year, month, day);
    if (birth.getMonth() !== month) return NaN;
    const today = new Date();
    let age = today.getFullYear() - year;
    if (today < new Date(today.getFullYear(), month, day)) age--;
    return birth > today ? -1 : age;
  };

  const questionCheck = id => () => {
    const v = $('#' + id).value;
    if (!v) return REQ;
    return ['qn1', 'qn2', 'qn3'].filter(other => $('#' + other).value === v).length > 1 ? 'Choose three different questions.' : '';
  };

  const answerCheck = id => () => {
    const v = $('#' + id).value;
    if (!v.trim()) return REQ;
    if (/ {2}/.test(v)) return 'Double space is not allowed.';
    if (!/^[A-Za-z]+( [A-Za-z]+)*$/.test(v) || v.length < 2 || v.length > 50) return 'Answer must be 2–50 letters and spaces only.';
    return '';
  };

  const captchaCheck = id => () => ($('#' + id).value.trim() ? '' : 'Captcha verification failed.');

  // what the server told us about the username (so the message survives re-checks)
  const usernameState = { value: '', available: null };

  const RULES = {
    rid: () => { const v = $('#rid').value; return !v ? REQ : /^\d{4}-\d{4}$/.test(v) ? '' : 'ID Number must follow the format xxxx-xxxx.'; },
    fn: nameCheck('fn', 'First Name'),
    mn: nameCheck('mn', 'Middle Name', true),
    ln: nameCheck('ln', 'Family Name'),
    ext: () => ($('#ext').value === 'Other' && !ROMAN.includes($('#ext_other').value) ? 'Please enter a valid name extension (I, II, III, IV, IX or X).' : ''),
    bd: () => {
      const age = ageFromForm();
      if (age === null || Number.isNaN(age)) return REQ;
      if (age < 0) return 'Birthdate cannot be in the future.';
      return age < 18 ? 'You must be at least 18 years old to register.' : '';
    },
    em: () => {
      const v = $('#em').value;
      if (!v) return REQ;
      return /\s/.test(v) || v.length > 100 || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) ? 'Please enter a valid email address.' : '';
    },
    un: () => {
      const v = $('#un').value;
      if (!v) return REQ;
      if (v.length < 6 || v.length > 20 || !/^[A-Za-z][A-Za-z0-9]*$/.test(v)) return 'Username must be 6–20 letters or digits, starting with a letter.';
      if (usernameState.value !== v) return '+Checking availability…';
      return usernameState.available ? '+Username is available.' : 'Username is already taken.';
    },
    pw: () => { const v = $('#pw').value; return !v ? REQ : strengthOf(v) < 2 ? weakMessage : ''; },
    pw2: () => { const v = $('#pw2').value; return !v || v !== $('#pw').value ? 'Passwords do not match.' : ''; },
    st: () => {
      const v = $('#st').value;
      if (!v.trim()) return REQ;
      if (/ {2}/.test(v)) return 'Double space is not allowed.';
      return /^[A-Za-z0-9 ]{3,50}$/.test(v.trim()) ? '' : 'Purok/Street must be 3–50 letters, digits and spaces.';
    },
    br: addressCheck('br', 'Barangay', 2, 50),
    ct: addressCheck('ct', 'Municipality/City', 2, 50),
    pv: addressCheck('pv', 'Province', 2, 50),
    co: addressCheck('co', 'Country', 2, 50),
    zp: () => { const v = $('#zp').value; return !v ? REQ : /^\d{4}$/.test(v) ? '' : 'ZIP Code must be exactly 4 digits.'; },
    cn: () => { const v = $('#cn').value; return !v ? REQ : /^09\d{9}$/.test(v) ? '' : 'Contact number must be 11 digits and start with 09.'; },
    sc: () => ($('#sc').value ? '' : REQ),
    pr: () => {
      const v = $('#pr').value;
      if (!v.trim()) return REQ;
      return /^[A-Za-z]+( [A-Za-z]+)*$/.test(v) && v.length >= 2 && v.length <= 50 ? '' : 'Course/Program must be 2–50 letters and single spaces only.';
    },
    qn1: questionCheck('qn1'), qn2: questionCheck('qn2'), qn3: questionCheck('qn3'),
    q1: answerCheck('q1'), q2: answerCheck('q2'), q3: answerCheck('q3'),
    rcap: captchaCheck('rcap'),
    consent: () => ($('#consent').checked ? '' : 'You must agree to the Privacy Notice to register.'),

    // log-in
    lu: () => ($('#lu').value.trim() ? '' : REQ),
    lp: () => ($('#lp').value ? '' : REQ),
    lcap: captchaCheck('lcap'),

    // forgot password
    fu: () => ($('#fu').value.trim() ? '' : REQ),
    fcap: captchaCheck('fcap'),
    fa: answerCheck('fa'),
    fa2: () => ($('#fa2').value !== $('#fa').value || !$('#fa2').value ? 'Answers do not match.' : ''),

    // change password
    cp1: () => { const v = $('#cp1').value; return !v ? REQ : strengthOf(v) < 2 ? weakMessage : ''; },
    cp2: () => ($('#cp2').value !== $('#cp1').value || !$('#cp2').value ? 'Mismatch Password' : ''),

    // one-time code
    otpcode: () => (/^\d{6}$/.test($('#otpcode').value) ? '' : 'Enter the 6-digit code.')
  };

  const ALIAS = { bm: 'bd', by: 'bd', ext_other: 'ext' };

  function showMessage(id, message) {
    const line = $('#e-' + id);
    if (!line) return;
    if (message && message[0] === '+') { line.className = 'err ok'; line.textContent = message.slice(1); }
    else { line.className = 'err'; line.textContent = message; }
  }

  function validate(id) {
    id = ALIAS[id] || id;
    const rule = RULES[id];
    if (!rule) return true;

    const message = rule();
    showMessage(id, message);

    const control = $('#' + id);
    if (control) control.setAttribute('aria-invalid', message && message[0] !== '+' ? 'true' : 'false');
    return !message || message[0] === '+';
  }

  const validateAll = ids => ids.map(validate).every(Boolean);   // map first so EVERY error shows


  /* ===================================================================
     5. REGISTER FORM BEHAVIOUR
     =================================================================== */
  const reg = $('#reg');

  function syncQuestions() {
    const chosen = ['qn1', 'qn2', 'qn3'].map(id => $('#' + id).value);
    ['qn1', 'qn2', 'qn3'].forEach((id, index) => {
      [...$('#' + id).options].forEach(option => {
        option.disabled = !!option.value && chosen.some((value, other) => other !== index && value === option.value);
      });
    });
  }

  function setValidity() {
    const date = new Date();
    date.setFullYear(date.getFullYear() + 4);
    $('#validity').textContent = 'This card is valid until ' +
      date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + '.';
  }

  // live "is this username taken?" check against the database
  let usernameTimer;
  function checkUsername() {
    clearTimeout(usernameTimer);
    const name = $('#un').value;
    const rule = RULES.un();
    if (rule && rule[0] !== '+') return;                          // format error: already showing

    usernameTimer = setTimeout(async () => {
      const answer = await H.get('php/check_username.php?u=' + encodeURIComponent(name));
      if (typeof answer.available === 'boolean' && $('#un').value === name) {
        usernameState.value = name;
        usernameState.available = answer.available;
        validate('un');
      }
    }, 350);
  }

  reg.addEventListener('focusout', event => {
    if (!event.target.id) return;
    event.target.dataset.t = 1;
    validate(event.target.id);
  });

  function meter(id, value) {
    const level = value ? strengthOf(value) : 0;
    const bar = $('#' + id + '-bar');
    bar.style.width = (level * 33.4) + '%';
    bar.style.background = ['', 'var(--crimson)', 'var(--gold)', '#2f9e5b'][level];
    $('#e-' + id).className = 'err';
    $('#e-' + id).textContent = level ? 'Strength: ' + ['', 'Weak', 'Medium', 'Strong'][level] : '';
  }

  reg.addEventListener('input', event => {
    const id = event.target.id;

    if (id === 'rid') {                                           // only digits; the hyphen is added for the user
      const digits = event.target.value.replace(/\D/g, '').slice(0, 8);
      event.target.value = digits.length > 4 ? digits.slice(0, 4) + '-' + digits.slice(4) : digits;
    }
    if (id === 'pw') {
      meter('pw', event.target.value);
      if ($('#pw2').dataset.t) validate('pw2');
    }
    if (id === 'un') checkUsername();

    if (RULES[id] || ALIAS[id]) {
      if (id !== 'pw') { event.target.dataset.t = 1; validate(id); }   // live errors on every keystroke
    }
  });

  reg.addEventListener('change', event => {
    const id = event.target.id;

    if (['bm', 'bd', 'by'].includes(id)) {
      const age = ageFromForm();
      $('#age').value = age > 0 ? age : '';
    }
    if (id === 'ext') {
      $('#ext_other').hidden = event.target.value !== 'Other';
      if ($('#ext_other').hidden) $('#ext_other').value = '';
    }
    if (RULES[id] || ALIAS[id]) validate(id);

    if (/^qn\d$/.test(id)) {
      syncQuestions();
      ['qn1', 'qn2', 'qn3'].forEach(question => {
        if ($('#' + question).value || $('#' + question).dataset.t) validate(question);
      });
    }
  });

  reg.addEventListener('submit', async event => {
    event.preventDefault();

    const ids = Object.keys(RULES).filter(id => reg.contains($('#e-' + id)));
    const invalid = ids.filter(id => !validate(id));
    if (invalid.length) { const first = $('#' + invalid[0]); if (first) first.focus(); return; }

    const button = reg.querySelector('.submit');
    button.disabled = true;
    $('#e-form').textContent = '';

    const data = new FormData(reg);
    data.append('captcha', $('#rcap').value);
    const result = await H.post('php/register.php', data);
    loadCaptcha('r');                                             // a new picture after every try

    if (result.ok && result.step === 'otp') {
      registeredValidUntil = result.validUntil || '';
      reg.reset();
      $('#pw-bar').style.width = 0;
      $('#age').value = '';
      $('#pw').type = 'password';
      reg.querySelector('[data-eye="pw"]').textContent = 'Show';
      $('#ext_other').hidden = true;
      syncQuestions();
      showOtp(result);
    } else if (result.errors) {
      Object.entries(result.errors).forEach(([key, message]) => showMessage(key === '_form' ? 'form' : key, message));
    } else if (!result.ok) {
      $('#e-form').textContent = result.error || 'Something went wrong.';
    }
    button.disabled = false;
  });


  /* ===================================================================
     6. ONE-TIME CODE (used after registering, after log-in, and for password reset)
     =================================================================== */
  let otpPurpose = '';
  let resendTimer;

  function startResendCountdown(seconds) {
    const button = $('#resend');
    clearInterval(resendTimer);
    button.disabled = true;
    let left = seconds;

    const tick = () => {
      if (left <= 0) {
        clearInterval(resendTimer);
        button.disabled = false;
        $('#resend-note').textContent = '';
      } else {
        $('#resend-note').textContent = `(you can ask again in ${left}s)`;
        left--;
      }
    };
    tick();
    resendTimer = setInterval(tick, 1000);
  }

  function showOtp(info) {
    otpPurpose = info.purpose;
    const titles = { register: 'Verify your email', login: 'Enter your log-in code', reset: 'Enter your reset code' };
    $('#t-otp').textContent = titles[info.purpose];
    $('#otp-msg').textContent = `We sent a 6-digit code to ${info.email}. It expires in 5 minutes.`;
    $('#otpcode').value = '';
    $('#e-otpcode').textContent = '';
    openPopup('otp', info.purpose === 'register' ? 'register' : 'login');
    startResendCountdown(60);
  }

  $('#otp').addEventListener('submit', async event => {
    event.preventDefault();
    if (!validate('otpcode')) return;

    const button = $('#otp-btn');
    button.disabled = true;
    const data = new FormData();
    data.append('code', $('#otpcode').value);
    const result = await H.post('php/otp_verify.php', data);
    button.disabled = false;

    if (!result.ok) {
      showMessage('otpcode', result.error || 'Something went wrong.');
      if (result.restart) setTimeout(() => openPopup('login'), 1200);
      return;
    }

    if (result.next === 'login') {                                // account verified
      H.toast('Registration successful', 'Your account is verified. ' + (registeredValidUntil ? 'Your card is valid until ' + registeredValidUntil + '.' : ''));
      openPopup('login');
    } else if (result.next === 'loggedin') {
      setLoggedIn(result.name, result.role);
      H.toast('Log-in successful', 'Welcome back, ' + result.name + '!');
      if (result.role === 'admin') setTimeout(() => { window.location.href = 'dashboard.html'; }, 900);
    } else if (result.next === 'change') {
      $('#cp1').value = ''; $('#cp2').value = '';
      openPopup('change', 'login');
    }
  });

  $('#resend').addEventListener('click', async () => {
    const result = await H.post('php/otp_resend.php', new FormData());
    if (result.ok) { showMessage('otpcode', '+A new code was sent.'); startResendCountdown(60); }
    else { showMessage('otpcode', result.error || 'Something went wrong.'); if (result.wait) startResendCountdown(result.wait); }
  });


  /* ===================================================================
     7. LOG-IN FORM: CAPTCHA, server lockout, Forgot Password link
     =================================================================== */
  let lockTimer;

  function startLock(seconds) {
    clearInterval(lockTimer);
    let left = seconds;
    const set = disabled => {
      $('#lbtn').disabled = disabled;
      $('#reglink').classList.toggle('disabled', disabled);        // "Please register here" is disabled too
      $('#reglink').setAttribute('aria-disabled', String(disabled));
    };
    set(true);

    const tick = () => {
      if (left <= 0) { clearInterval(lockTimer); set(false); $('#e-lock').textContent = ''; return; }
      $('#e-lock').className = 'err';
      $('#e-lock').textContent = `Too many attempts. Try again in ${left}s.`;
      left--;
    };
    tick();
    lockTimer = setInterval(tick, 1000);
  }

  $('#login').addEventListener('input', event => {
    if (RULES[event.target.id]) { event.target.dataset.t = 1; validate(event.target.id); }
  });

  $('#login').addEventListener('submit', async event => {
    event.preventDefault();
    $('#e-lock').textContent = '';
    if (!validateAll(['lu', 'lp', 'lcap'])) return;

    const button = $('#lbtn');
    button.disabled = true;
    const data = new FormData();
    data.append('username', $('#lu').value.trim());
    data.append('password', $('#lp').value);
    data.append('captcha', $('#lcap').value);
    const result = await H.post('php/login.php', data);
    loadCaptcha('l');
    button.disabled = false;

    if (result.ok) { $('#lp').value = ''; showOtp(result); return; }

    if (result.field === 'lcap') showMessage('lcap', result.error);
    else if (result.locked > 0 || result.blocked) showMessage('lock', result.error);
    else showMessage('lp', result.error || 'Something went wrong.');

    if (result.consecutive >= 2) $('#forgot').hidden = false;      // appears after 2 failed log-ins
    if (result.locked > 0) startLock(result.locked);
  });


  /* ===================================================================
     8. FORGOT PASSWORD: username -> question -> OTP -> new password
     =================================================================== */
  $('#fp1').addEventListener('input', event => { if (RULES[event.target.id]) validate(event.target.id); });
  $('#fp2').addEventListener('input', event => { if (RULES[event.target.id]) validate(event.target.id); });

  $('#fp1').addEventListener('submit', async event => {
    event.preventDefault();
    if (!validateAll(['fu', 'fcap'])) return;

    const data = new FormData();
    data.append('username', $('#fu').value.trim());
    data.append('captcha', $('#fcap').value);
    const result = await H.post('php/forgot_start.php', data);
    loadCaptcha('f');

    if (!result.ok) {
      if (result.field === 'fcap') showMessage('fcap', result.error);
      else showMessage('fform', result.error || 'Something went wrong.');
      return;
    }

    $('#fq').innerHTML = result.questions.map((q, index) =>
      `<label class="radio"><input type="radio" name="qno" value="${q.no}" ${index === 0 ? 'checked' : ''}> <span></span></label>`).join('');
    $('#fq').querySelectorAll('span').forEach((span, index) => { span.textContent = result.questions[index].text; });   // textContent: safe
    $('#fa').value = ''; $('#fa2').value = '';
    $('#fp1').hidden = true;
    $('#fp2').hidden = false;
  });

  $('#fp2').addEventListener('submit', async event => {
    event.preventDefault();
    if (!validateAll(['fa', 'fa2'])) return;

    const data = new FormData();
    data.append('qno', ($('#fq input:checked') || {}).value || '');
    data.append('answer', $('#fa').value);
    data.append('answer2', $('#fa2').value);
    const result = await H.post('php/forgot_answer.php', data);

    if (result.ok) { showOtp(result); return; }
    if (result.restart) { openPopup('forgot', 'login'); return; }
    if (result.field) showMessage(result.field, result.error);
    else showMessage('fform2', result.error || 'Something went wrong.');
  });


  /* ===================================================================
     9. CHANGE PASSWORD (only opens after the server accepted the reset OTP)
     =================================================================== */
  $('#change').addEventListener('input', event => {
    if (event.target.id === 'cp1') meter('cp1', event.target.value);
    else if (RULES[event.target.id]) validate(event.target.id);
  });

  $('#change').addEventListener('submit', async event => {
    event.preventDefault();
    if (!validateAll(['cp1', 'cp2'])) return;

    const data = new FormData();
    data.append('pw', $('#cp1').value);
    data.append('pw2', $('#cp2').value);
    const result = await H.post('php/change_password.php', data);

    if (result.ok) {
      H.toast('Successfully Change Password', 'You can now log in with your new password.');
      openPopup('login');
    } else if (result.restart) {
      showMessage('cform', result.error);
    } else if (result.field) {
      showMessage(result.field, result.error);
    } else {
      showMessage('cform', result.error || 'Something went wrong.');
    }
  });


  /* ===================================================================
     10. SESSION: who is logged in, idle timeout, log-out
     =================================================================== */
  function setLoggedIn(name, role) {
    loggedIn = true;
    closeAll();
    setState('loggedin');
    $('#welcome').hidden = false;
    $('#welcome b').textContent = name;
    $('#dashlink').hidden = role !== 'admin';
    H.startIdle(900, () => logout(true));
  }

  async function logout(becauseIdle) {
    await H.post('php/logout.php', new FormData());
    loggedIn = false;
    H.stopIdle();
    $('#welcome').hidden = true;
    closeAll();
    setState('home');
    if (becauseIdle) H.toast('Logged out', 'You were logged out because of inactivity.', { kind: 'warn' });
    else H.toast('Logged out', 'You have been logged out.', { kind: 'info' });
  }

  async function checkSession() {
    const me = await H.get('php/me.php');
    if (me.loggedIn) setLoggedIn(me.name, me.role);
    else if (loggedIn) { loggedIn = false; H.stopIdle(); $('#welcome').hidden = true; setState('home'); }
  }
  window.addEventListener('pageshow', event => { if (event.persisted) checkSession(); });   // the browser Back button


  /* ===================================================================
     11. CATEGORY CARDS (just the highlight) AND START-UP
     =================================================================== */
  document.querySelectorAll('.cat').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.cat').forEach(other => other.classList.remove('active'));
      card.classList.add('active');
    });
  });

  setValidity();
  setState('home');
  checkSession();

  // Links from other pages and from emails: index.html#register, #login, #forgot
  if (location.hash === '#register') openPopup('register');
  else if (location.hash === '#login') openPopup('login');
  else if (location.hash === '#forgot') openPopup('forgot', 'login');
})();
