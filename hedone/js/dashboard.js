/* =====================================================================
   Hedone Fresh – admin dashboard script
   The server decides who may see the data (php/dashboard_data.php).
   Text that came from the log is escaped by PHP (htmlspecialchars) before it gets here.
   ===================================================================== */
(function () {
  'use strict';

  const H = window.Hedone;
  const $ = H.$;
  let typesFilled = false;


  /* ---------- header links (same rule as every page) ---------- */
  function setHeader(loggedIn) {
    $('#hdrlinks').innerHTML = loggedIn
      ? '<a href="index.html">Home</a><a href="#" id="logout">Log-out</a>'
      : '<a href="index.html">Home</a><a href="index.html#login">Log-in</a>';

    const logoutLink = $('#logout');
    if (logoutLink) logoutLink.addEventListener('click', async event => { event.preventDefault(); await logout(); });
  }

  async function logout() {
    await H.post('php/logout.php', new FormData());
    H.stopIdle();
    window.location.href = 'index.html';
  }


  /* ---------- drawing the data ---------- */
  function table(id, headings, rows) {
    const head = '<thead><tr>' + headings.map(text => `<th>${text}</th>`).join('') + '</tr></thead>';
    const body = rows.length
      ? rows.map(cells => '<tr>' + cells.map(cell => `<td>${cell}</td>`).join('') + '</tr>').join('')
      : `<tr><td colspan="${headings.length}">Nothing to show.</td></tr>`;
    $(id).innerHTML = head + '<tbody>' + body + '</tbody>';
  }

  function draw(data) {
    $('#cards').innerHTML = data.cards
      .map(card => `<div class="card"><b>${Number(card[1])}</b><span>${card[0]}</span></div>`)
      .join('');

    const most = Math.max(1, ...data.hours.map(hour => hour.count));
    $('#bars').innerHTML = '';
    data.hours.forEach(hour => {
      const bar = document.createElement('div');
      bar.className = 'bar';
      bar.title = `${hour.label}: ${hour.count} failed log-ins`;
      bar.innerHTML = '<span class="n"></span><i></i><span class="l"></span>';
      bar.querySelector('.n').textContent = hour.count || '';
      bar.querySelector('i').style.height = Math.max(2, (hour.count / most) * 100) + '%';
      bar.querySelector('.l').textContent = hour.label;
      $('#bars').appendChild(bar);
    });

    table('#ips', ['IP address', 'Failures', 'Blocked now?'],
      data.ips.map(row => [row.ip, Number(row.count), row.blocked ? 'Yes' : 'No']));
    table('#flags', ['Pattern', 'Severity', 'Target', 'Details', 'Date'],
      data.flags.map(row => [row.pattern, row.severity, row.target, row.details, row.flag_date]));
    table('#events', ['Time', 'Event', 'Username typed', 'User ID', 'IP address', 'Details'],
      data.events.map(row => [row.created_at, row.event_type, row.username_typed, row.user_id, row.ip_address, row.details]));

    if (!typesFilled) {                                           // fill the event dropdown once
      data.types.forEach(type => {
        const option = document.createElement('option');
        option.textContent = type;
        option.value = type;
        $('#f-type').appendChild(option);
      });
      typesFilled = true;
    }
    $('#refreshed').textContent = 'Last refreshed at ' + new Date().toLocaleTimeString() + '. This page refreshes every 60 seconds.';
  }


  /* ---------- asking the server ---------- */
  function queryString(firstTime) {
    const query = new URLSearchParams();
    if (firstTime) query.set('open', '1');                        // only the first load is logged as "dashboard opened"
    if ($('#f-from').value) query.set('from', $('#f-from').value);
    if ($('#f-to').value) query.set('to', $('#f-to').value);
    if ($('#f-type').value) query.set('type', $('#f-type').value);
    if ($('#f-user').value.trim()) query.set('username', $('#f-user').value.trim());
    if ($('#f-ip').value.trim()) query.set('ip', $('#f-ip').value.trim());
    return query.toString();
  }

  async function load(firstTime) {
    const data = await H.get('php/dashboard_data.php?' + queryString(firstTime));

    if (data.status === 403 || (!data.ok && !data.network)) {
      $('#dash').hidden = true;
      $('#dash-msg').className = 'err';
      $('#dash-msg').textContent = 'Access denied. This page is for administrators only.';
      const me = await H.get('php/me.php');
      setHeader(!!me.loggedIn);
      return false;
    }
    if (data.network) { $('#dash-msg').textContent = data.error; return false; }

    setHeader(true);
    $('#dash-msg').textContent = 'Welcome, ' + data.name + '.';
    $('#dash').hidden = false;
    draw(data);
    return true;
  }


  /* ---------- start-up ---------- */
  setHeader(false);

  load(true).then(allowed => {
    if (!allowed) return;
    H.startIdle(900, logout);                                     // same 15-minute idle timeout as the rest of the site
    setInterval(() => load(false), 60000);                        // auto-refresh every 60 seconds
  });

  $('#filters').addEventListener('submit', event => {
    event.preventDefault();
    load(false);
  });
})();
