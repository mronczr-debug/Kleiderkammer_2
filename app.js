// Global variables
let signaturePad;

document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll("nav a");
  const content = document.getElementById("content");

  // Central view loader
  function loadView(view) {
    fetch(`views/${view}.html`)
      .then(res => res.text())
      .then(html => {
        content.innerHTML = html;
        history.pushState({}, "", `#${view}`);
        setActiveLink(view);

        setTimeout(() => {
          switch (view) {
            case "mitarbeiter":
              loadMitarbeiter();
              break;
            case "artikel":
              loadArtikel();
              break;
            case "bestaende":
              loadBestaende();
              break;
            case "ausgabe":
              initAusgabe();
              break;
            case "wareneingang":
              loadWareneingang();
              break;
            case "reporting":
              loadReporting();
              break;
            case "home":
              // nothing to do
              break;
          }
        }, 50);
      });
  }

  // Highlight active sidebar link
  function setActiveLink(view) {
    links.forEach(link => {
      link.classList.toggle("active", link.dataset.view === view);
    });
  }

  // Sidebar navigation
  links.forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      loadView(link.dataset.view);
    });
  });

  // Initial load from hash or default to home
  const initialView = window.location.hash.substring(1) || "home";
  loadView(initialView);
});


// ———————————— Mitarbeiter Functions ————————————

function loadMitarbeiter() {
  fetch("api/get_mitarbeiter.php")
    .then(r => r.json())
    .then(data => {
      const tbody = document.querySelector("#mitarbeiter-tabelle tbody");
      const suchfeld = document.getElementById("mitarbeiter-suche");
      if (!tbody) return;

      const render = (filter = "") => {
        const rows = data
          .filter(m => `${m.vorname} ${m.nachname} ${m.abteilung}`.toLowerCase().includes(filter.toLowerCase()))
          .map(m => `
            <tr>
              <td>${m.nachname}, ${m.vorname}</td>
              <td>${m.abteilung}</td>
              <td>${m.kleidergroesse}</td>
              <td>${m.schuhgroesse}</td>
              <td>${m.eintrittsdatum}</td>
              <td>${m.typ}</td>
              <td><button class="btn btn-secondary" onclick="editMitarbeiter(${m.id})">✏️</button></td>
            </tr>`).join("");
        tbody.innerHTML = rows;
      };

      suchfeld?.addEventListener("input", e => render(e.target.value));
      render();
    });
}

function openMitarbeiterPopup() {
  document.getElementById("mitarbeiter-popup").style.display = "flex";
}
function closeMitarbeiterPopup() {
  document.getElementById("mitarbeiter-popup").style.display = "none";
}

function editMitarbeiter(id) {
  fetch(`api/get_mitarbeiter_by_id.php?id=${id}`)
    .then(r => r.json())
    .then(m => {
      const f = document.getElementById("mitarbeiter-form");
      ["id", "vorname", "nachname", "abteilung", "kleidergroesse", "schuhgroesse", "eintrittsdatum", "typ"]
        .forEach(name => f[name].value = m[name]);
      openMitarbeiterPopup();
    });
}


// ———————————— Artikel Functions ————————————

function loadArtikel() {
  fetch("api/get_kleidung.php")
    .then(r => r.json())
    .then(data => {
      const tbody = document.querySelector("#artikel-tabelle tbody");
      const suchfeld = document.getElementById("artikel-suche");
      if (!tbody) return;

      const render = (filter = "") => {
        const rows = data
          .filter(a => `${a.bezeichnung} ${a.groesse} ${a.gruppe}`.toLowerCase().includes(filter.toLowerCase()))
          .map(a => `
            <tr>
              <td>${a.bezeichnung}</td>
              <td>${a.gruppe}</td>
              <td>${a.groesse}</td>
              <td><button class="btn btn-secondary" onclick="editArtikel(${a.id})">✏️</button></td>
            </tr>`).join("");
        tbody.innerHTML = rows;
      };

      suchfeld?.addEventListener("input", e => render(e.target.value));
      render();
    });
}

function openArtikelPopup() {
  document.getElementById("artikel-popup").style.display = "flex";
}
function closeArtikelPopup() {
  document.getElementById("artikel-popup").style.display = "none";
}

function editArtikel(id) {
  fetch(`api/get_kleidung_by_id.php?id=${id}`)
    .then(r => r.json())
    .then(a => {
      const f = document.getElementById("artikel-form");
      ["id", "bezeichnung", "groesse", "gruppe"].forEach(name => f[name].value = a[name]);
      openArtikelPopup();
    });
}


// ———————————— Bestaende Functions ————————————

function loadBestaende() {
  fetch("api/get_bestaende.php")
    .then(r => r.json())
    .then(data => {
      const tbody = document.querySelector("#bestaende-tabelle tbody");
      if (!tbody) return;
      tbody.innerHTML = data.map(b => `
        <tr>
          <td>${b.bezeichnung}</td>
          <td>${b.groesse}</td>
          <td>${b.bestand}</td>
          <td><button class="btn btn-secondary" onclick="openBestandPopup(${b.artikel_id}, '${b.groesse}', ${b.bestand})">✏️</button></td>
        </tr>`).join("");
    });
}

function openBestandPopup(id, groesse, bestand) {
  const f = document.getElementById("bestand-form");
  f.kleidung_id.value = id;
  f.groesse.value = groesse;
  f.neuer_bestand.value = bestand;
  document.getElementById("bestand-popup").style.display = "flex";
}
function closeBestandPopup() {
  document.getElementById("bestand-popup").style.display = "none";
}


// ———————————— Ausgabe (Kleidung ausgeben) ————————————

function initAusgabe() {
  loadMitarbeiterDropdown();
  addAusgabePosition();
  loadRecentAusgaben();
}

function loadMitarbeiterDropdown() {
  fetch("api/get_mitarbeiter.php")
    .then(r => r.json())
    .then(data => {
      const sel = document.querySelector("select[name='mitarbeiter_id']");
      if (!sel) return;
      sel.innerHTML = data.map(m => `<option value="${m.id}">${m.nachname}, ${m.vorname}</option>`).join("");
    });
}

function addAusgabePosition() {
  fetch("api/get_kleidung.php")
    .then(r => r.json())
    .then(artikel => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>
          <select name="artikel_id[]" required onchange="loadGroessen(this)">
            ${artikel.map(a => `<option value="${a.id}">${a.bezeichnung}</option>`).join("")}
          </select>
        </td>
        <td>
          <select name="groesse[]" required>
            <option>— Größe wählen —</option>
          </select>
        </td>
        <td><input name="menge[]" type="number" min="1" required /></td>
        <td><button type="button" class="btn" onclick="this.closest('tr').remove()">🗑️</button></td>`;
      document.querySelector("#ausgabe-positionen tbody").appendChild(row);
    });
}

function loadGroessen(artSel) {
  const tr = artSel.closest('tr');
  const sizeSel = tr.querySelector("select[name='groesse[]']");
  sizeSel.innerHTML = `<option>Lädt…</option>`;
  fetch(`api/get_article_sizes.php?artikel_id=${artSel.value}`)
    .then(r => r.json())
    .then(sizes => {
      sizeSel.innerHTML = '<option value="">— Größe wählen —</option>' +
        sizes.map(s => `<option>${s}</option>`).join("");
    });
}

function openSignaturePopup() {
  signaturePad && signaturePad.clear();
  initSignaturePad();
  document.getElementById("signature-popup").style.display = "flex";
}

function closeSignaturePopup() {
  document.getElementById("signature-popup").style.display = "none";
}

function confirmAusgabe() {
  if (!signaturePad || signaturePad.isEmpty()) {
    alert("Bitte unterschreiben.");
    return;
  }
  document.getElementById("unterschrift").value = signaturePad.toDataURL("image/png");

  const form = document.getElementById("ausgabe-form");
  const fd = new FormData(form);
  fetch("api/add_ausgabe.php", { method: "POST", body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert("Ausgabe gespeichert!");
        form.reset();
        document.querySelector("#ausgabe-positionen tbody").innerHTML = "";
        loadRecentAusgaben();
        closeSignaturePopup();
      } else {
        alert("Fehler: " + res.error);
      }
    });
}

function loadRecentAusgaben() {
  fetch("api/get_ausgaben_per_mitarbeiter.php")
    .then(r => r.json())
    .then(data => {
      const tbody = document.querySelector("#recent-ausgaben-table tbody");
      if (!tbody) return;
      tbody.innerHTML = data.slice(0, 10).map(d => `
        <tr>
          <td>${d.ausgabe_id}</td>
          <td>${d.nachname}, ${d.vorname}</td>
          <td>${d.datum}</td>
          <td>${d.bezeichnung}</td>
          <td>${d.groesse}</td>
          <td>${d.menge}</td>
          <td><button class="btn btn-secondary" onclick="openQuittung(${d.ausgabe_id})">Quittung</button></td>
        </tr>`).join("");
    });
}


// ———————————— Wareneingang ————————————

function loadWareneingang() {
  addEingangPosition();
}

function addEingangPosition() {
  fetch("api/get_kleidung.php")
    .then(r => r.json())
    .then(items => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>
          <select name="artikel_id[]">${items.map(i => `<option value="${i.id}">${i.bezeichnung}</option>`).join("")}</select>
        </td>
        <td><input name="groesse[]" placeholder="Größe" /></td>
        <td><input name="menge[]" type="number" min="1" required /></td>
        <td><button type="button" class="btn" onclick="this.closest('tr').remove()">🗑️</button></td>`;
      document.querySelector("#eingang-positionen tbody").appendChild(row);
    });
}


// ———————————— Reporting Übersicht ————————————

function loadReporting() {
  fetch("api/get_ausgaben_per_mitarbeiter.php")
    .then(r => r.json())
    .then(data => {
      const filter = document.getElementById("reporting-filter");
      const tbody = document.querySelector("#reporting-tabelle tbody");
      const unique = [...new Set(data.map(d => `${d.mitarbeiter_id}|${d.nachname}, ${d.vorname}`))];
      filter.innerHTML = '<option value="">Alle</option>' + unique.map(u => {
        const [id, name] = u.split("|");
        return `<option value="${id}">${name}</option>`;
      }).join("");
      filter.addEventListener("change", () => render(data));

      function render(arr) {
        tbody.innerHTML = arr
          .filter(d => !filter.value || d.mitarbeiter_id == filter.value)
          .map(d => `
            <tr>
              <td>${d.nachname}, ${d.vorname}</td>
              <td>${d.datum}</td>
              <td>${d.bezeichnung}</td>
              <td>${d.groesse}</td>
              <td>${d.menge}</td>
              <td><button class="btn btn-secondary" onclick="openQuittung(${d.ausgabe_id})">Quittung</button></td>
            </tr>`).join("");
      }
      render(data);
    });
}

function openQuittung(id) {
  window.open(`api/generate_quittung.php?id=${id}`, "_blank");
}
