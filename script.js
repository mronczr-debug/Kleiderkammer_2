document.addEventListener("DOMContentLoaded", () => {
  const mitarbeiterForm = document.getElementById("mitarbeiter-form");
  const ausgabeForm = document.getElementById("ausgabe-form");
  const mitarbeiterSelect = ausgabeForm.mitarbeiter_id;
  const kleidungSelect = ausgabeForm.kleidung_id;
  const lagerForm = document.getElementById("lager-form");
  const lagerTabelleBody = document.querySelector("#lager-tabelle tbody");	

  function loadMitarbeiter() {
    fetch("api/get_mitarbeiter.php")
      .then(res => res.json())
      .then(data => {
        mitarbeiterSelect.innerHTML = data.map(m => 
          `<option value="${m.id}">${m.name}</option>`
        ).join("");
      });
  }

  function loadKleidung() {
    fetch("api/get_kleidung.php")
      .then(res => res.json())
      .then(data => {
        kleidungSelect.innerHTML = data.map(k =>
          `<option value="${k.id}">${k.bezeichnung} (${k.groesse})</option>`
        ).join("");
      });
  }

  function loadLagerbewegungen() {
  fetch("api/get_lagerbewegungen.php")
    .then(res => res.json())
    .then(data => {
      lagerTabelleBody.innerHTML = data.map(e => `
        <tr>
          <td>${new Date(e.datum).toLocaleString()}</td>
          <td>${e.typ}</td>
          <td>${e.bezeichnung} (${e.groesse})</td>
          <td>${e.menge}</td>
          <td>${e.bemerkung || ''}</td>
        </tr>
      `).join("");
    });
}

  mitarbeiterForm.addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(mitarbeiterForm);
    fetch("api/add_mitarbeiter.php", {
      method: "POST",
      body: formData
    }).then(() => {
      mitarbeiterForm.reset();
      loadMitarbeiter();
    });
  });

  ausgabeForm.addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(ausgabeForm);
    fetch("api/add_ausgabe.php", {
      method: "POST",
      body: formData
    }).then(() => {
      ausgabeForm.reset();
    });
  });

lagerForm.addEventListener("submit", e => {
  e.preventDefault();
  const formData = new FormData(lagerForm);
  fetch("api/add_lagerbewegung.php", {
    method: "POST",
    body: formData
  }).then(() => {
    lagerForm.reset();
    loadKleidung();           // Bestand potenziell aktualisieren
    loadLagerbewegungen();    // Liste aktualisieren
  });
});

// Initial laden
loadMitarbeiter();
loadKleidung();
loadLagerbewegungen();
});
