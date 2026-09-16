/* ---------------------------------------------------------------------------
 * Zakkencalculator — "welke zak past in mijn bak?"
 *
 * De rekenregels komen uit "Berekening zak in bak.xls" (tabbladen vierkant en
 * rond), met twee correcties die daar nog ontbraken:
 *
 *  1. In het ronde tabblad stond lengte = hoogte + omslag. De bodem ontbrak
 *     daar; de notitie "middelpunt van de bak naar de rand" in dat blad laat
 *     zien dat de straal wel bedoeld was. Hier zit hij in de formule.
 *  2. De Excel rekent met de platte breedte van de zak. Dat werkt alleen bij
 *     zakken zonder zijvouw. We rekenen daarom in OMTREK, want dat is de enige
 *     maat die voor beide soorten zakken hetzelfde betekent.
 *
 * Twee formules, meer is het niet:
 *
 *     omtrek van de bak  = 2 x (kort + lang)     of   pi x diameter
 *     lengte van de zak  = hoogte + halve bodemmaat + omslag
 *
 * De halve bodemmaat is wat de zak opsnoept om de bodem plat te kunnen leggen;
 * de omslag is wat er over de rand heen moet steken. De "halve omtrek" die de
 * Excel als breedte teruggeeft is precies omtrek / 2 — we tonen hem er dus bij,
 * want dat is de maat die op de verpakking staat.
 *
 * Dit bestand is de ENIGE calculator-code. Het verving het inline script uit de
 * Elementor Custom-Code-snippet "Zakkencalculator" (post #4954, Body End,
 * conditie Page #200); die snippet is inmiddels verwijderd.
 *
 * Let op bij wijzigen: dit bestand leunde aanvankelijk op dat snippet voor het
 * radio-gedrag van Vierkant/Rond. Dat zit nu hieronder in kiesVorm(), zodat er
 * niets meer buiten het thema nodig is. Submit wordt op DOCUMENT-niveau in de
 * capture-fase onderschept, dus vóór een eventuele listener op het formulier.
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    // Wat er over de rand van de bak heen moet steken, in cm. Genoeg om de zak
    // om te slaan zonder dat hij terugzakt.
    const OMSLAG_CM = 5;

    // Speling in de suggestie. Te krap past niet, te ruim klapt open en zakt
    // weg in de bak. Dit is een advies, geen harde eis — vandaar een venster.
    const OMTREK_MARGE = 1.25;   // max 25% wijder dan nodig
    const LENGTE_MARGE = 1.35;   // max 35% langer dan nodig

    const form = document.getElementById('form-zakkencalculator');
    if (!form) return;

    const veld = {
        vierkant : form.querySelector('#checkbox-vierkant'),
        rond     : form.querySelector('#checkbox-round'),
        breedte  : form.querySelector('#input-width'),
        diepte   : form.querySelector('#input-square-depth'),
        hoogte   : form.querySelector('#input-calculator-height'),
        diameter : form.querySelector('#input-calculator-diameter'),
    };
    if (!veld.rond || !veld.hoogte) return;

    const isRond = () => !!(veld.rond && veld.rond.checked);

    /* --- Velden tonen per vorm ---------------------------------------------
       De Elementor-classes kloppen hier niet: het hoogteveld draagt class
       "input-square", waardoor het verdween zodra je Rond koos, en het
       breedteveld staat buiten beide groepen en bleef dus altijd staan. We
       sturen daarom per veld-id, niet per class. */
    function toonVelden() {
        const rond = isRond();
        const zichtbaar = rond
            ? [veld.diameter, veld.hoogte]
            : [veld.breedte, veld.diepte, veld.hoogte];

        // De vier invoervelden zijn directe buren in één Elementor-container;
        // er is geen wrapper per veld. Dus we schakelen het veld zelf. En we
        // zetten expliciet 'block' in plaats van '' terug te draaien: op
        // .input-round staat een Elementor-regel display:none, die een lege
        // inline-waarde niet overstemt.
        [veld.breedte, veld.diepte, veld.hoogte, veld.diameter].forEach((el) => {
            if (el) el.style.display = zichtbaar.indexOf(el) !== -1 ? 'block' : 'none';
        });

        // Zelfde val als bij de invoervelden hierboven: expliciet 'block', niet ''.
        // Elementor verbergt de ronde afbeelding standaard
        // (.elementor .e-e26be0d-b492ae7{display:none} in local-200-frontend-*.css),
        // en een lege inline-waarde haalt alleen de inline stijl weg — daarna
        // wint die regel gewoon weer en blijft de afbeelding onzichtbaar.
        const vierkantImg = document.getElementById('calc-square-img');
        const rondImg     = document.getElementById('calc-round-img');
        if (vierkantImg) vierkantImg.style.display = rond ? 'none' : 'block';
        if (rondImg)     rondImg.style.display     = rond ? 'block' : 'none';
    }

    /* Vierkant en Rond zijn in Elementor checkboxes, maar horen zich als radio's
       te gedragen: precies één van de twee staat aan.

       Dat regelde vroeger het inline script uit de Elementor Custom-Code-snippet
       "Zakkencalculator" (post #4954). Die snippet is weg, en daarmee verdween
       ook het uitvinken van de ander — je zag dan beide knoppen tegelijk actief.
       Daarom doet dit bestand het nu zelf, en is het niet langer afhankelijk van
       iets wat buiten het thema staat. */
    function kiesVorm(gekozen) {
        const ander = gekozen === veld.rond ? veld.vierkant : veld.rond;
        // je kunt de actieve knop niet uitzetten — er moet altijd een vorm aan staan
        gekozen.checked = true;
        if (ander) ander.checked = false;
        toonVelden();
    }

    [veld.vierkant, veld.rond].forEach((cb) => {
        if (cb) cb.addEventListener('change', () => setTimeout(() => kiesVorm(cb), 0));
    });

    // Begintoestand: staat er niets aan (of allebei), dan is Vierkant de standaard.
    if (veld.vierkant && veld.rond && veld.vierkant.checked === veld.rond.checked) {
        veld.vierkant.checked = true;
        veld.rond.checked = false;
    }

    toonVelden();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(toonVelden, 0));
    }

    const getal = (el) => {
        const n = parseFloat(String(el && el.value).replace(',', '.'));
        return Number.isFinite(n) && n > 0 ? n : 0;
    };

    /* --- De rekenkern ------------------------------------------------------ */
    function bereken() {
        const hoogte = getal(veld.hoogte);

        if (isRond()) {
            const diameter = getal(veld.diameter);
            if (!diameter || !hoogte) return { fout: 'Vul de diameter en de hoogte van de bak in.' };
            return maak(Math.PI * diameter, diameter, hoogte);
        }

        // Welke zijde de bezoeker "breedte" en welke hij "diepte" noemt, maakt
        // voor de omtrek niet uit, maar voor de lengte wel: de bodem vouwt over
        // de LANGSTE zijde. Daarom sorteren we zelf, dan kan hij het niet fout
        // invullen.
        const a = getal(veld.breedte);
        const b = getal(veld.diepte);
        if (!a || !b || !hoogte) return { fout: 'Vul de breedte, diepte en hoogte van de bak in.' };
        return maak(2 * (a + b), Math.max(a, b), hoogte);
    }

    function maak(omtrekNodig, bodemmaat, hoogte) {
        const lengteNodig = hoogte + bodemmaat / 2 + OMSLAG_CM;
        return {
            omtrekNodig,
            lengteNodig,
            // de maat zoals hij op de verpakking staat: platte breedte = halve omtrek
            breedteNodig: omtrekNodig / 2,
            omtrekRange : [Math.ceil(omtrekNodig), Math.round(omtrekNodig * OMTREK_MARGE)],
            lengteRange : [Math.ceil(lengteNodig), Math.round(lengteNodig * LENGTE_MARGE)],
        };
    }

    const cm = (n) => (Math.round(n * 10) / 10).toString().replace('.', ',');

    /* --- Doorsturen naar de productenzoeker --------------------------------
       Staat de zoeker op dezelfde pagina (dat is zo op /onze-producten/), dan
       zetten we de filters direct — geen herlaadbeurt. Anders vallen we terug
       op de deeplink-taal uit algolia-product-search.js: ?omtrek=211-264. */
    function toonZakken(res) {
        const ais = window.DIM_AIS;
        const params = `omtrek=${res.omtrekRange[0]}-${res.omtrekRange[1]}` +
                       `&lengte=${res.lengteRange[0]}-${res.lengteRange[1]}`;

        if (!ais || !ais.indexName) {
            window.location.href = `/onze-producten/?${params}`;
            return;
        }

        const idx = ais.indexName;
        const huidig = (ais.getUiState && ais.getUiState()[idx]) || {};
        ais.setUiState({
            [idx]: Object.assign({}, huidig, {
                page: 1,
                range: {
                    omtrek_cm: `${res.omtrekRange[0]}:${res.omtrekRange[1]}`,
                    lengte_cm: `${res.lengteRange[0]}:${res.lengteRange[1]}`,
                },
            }),
        });

        const doel = document.getElementById('dim-product-search-ais-hits');
        if (doel) doel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* --- Uitkomst tonen ---------------------------------------------------- */
    function toon(res) {
        const vak = form.querySelector('.e-form-success-message-base');
        if (!vak) return;
        vak.style.display = 'block';
        vak.innerHTML = '';

        if (res.fout) {
            vak.textContent = res.fout;
            return;
        }

        const blok = document.createElement('div');
        blok.className = 'dim-calc-advies';
        blok.innerHTML =
            '<p class="dim-calc-advies__maat">U heeft een zak nodig van ' +
            '<strong>' + cm(res.breedteNodig) + ' x ' + cm(res.lengteNodig) + ' cm</strong>.</p>' +
            '<p class="dim-calc-advies__toelichting">Dat is een omtrek van ' + cm(res.omtrekNodig) +
            ' cm. Heeft de zak een zijvouw, dan mag de platte breedte kleiner zijn ' +
            'de zoeker hieronder rekent dat voor u om.</p>';

        // const knop = document.createElement('button');
        // knop.type = 'button';
        // knop.className = 'dim-calc-advies__knop';
        // knop.textContent = 'Toon passende zakken';
        // knop.addEventListener('click', () => toonZakken(res));
        // blok.appendChild(knop);

        vak.appendChild(blok);
    }

    /* --- Submit onderscheppen ----------------------------------------------
       Capture op document: dit draait vóór de capture-listener op het formulier
       zelf, dus vóór het oude inline script én vóór Elementor's eigen mailer. */
    /* Berekenen in plaats van versturen.
       Twee ingangen, want het Atomic Form van Elementor vangt allebei:

         submit  — als de bezoeker Enter drukt in een invoerveld
         click   — als hij op "Bereken" drukt; Elementor hangt daar zijn eigen
                   afhandeling aan en die kwam vóór onze submit-listener. Je
                   kreeg dan het formulierbericht "Great! We've received your
                   information." te zien in plaats van de uitkomst.

       Allebei in de capture-fase op document, dus vóór de listeners van
       Elementor op de knop en op het formulier zelf. */
    function berekenEnToon(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        toon(bereken());
    }

    document.addEventListener('submit', function (e) {
        if (e.target !== form) return;
        berekenEnToon(e);
    }, true);

    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        // Let op: NIET matchen op [type="submit"]. Elementor schrijft dat
        // attribuut niet in de HTML — een <button> ín een formulier is van
        // zichzelf al submit. De property zegt "submit", de attribuutselector
        // vindt niets, en dan liep de klik gewoon door naar Elementor.
        const knop = e.target.closest('button, input');
        if (!knop || knop.type !== 'submit' || !form.contains(knop)) return;
        berekenEnToon(e);
    }, true);
})();
