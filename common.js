(function () {
    const logo = 'logokp.png';
    const logoMarkup = `<img src="${logo}" alt="KivuPass" class="site-logo-image">`;

    if (!document.querySelector('[data-site-shell="navbar"]')) {
        const nav = document.createElement('nav');
        nav.className = 'site-navbar';
        nav.dataset.siteShell = 'navbar';
        nav.innerHTML = `<a href="index.html" class="site-brand">${logoMarkup}</a>
            <div class="site-nav-links"><a href="index.html">Accueil</a><a href="enterprise.html">Espace armateur</a></div>`;
        document.body.prepend(nav);
    }

    if (!document.querySelector('[data-site-shell="footer"]')) {
        const footer = document.createElement('footer');
        footer.className = 'site-footer';
        footer.dataset.siteShell = 'footer';
        footer.innerHTML = `<div class="site-footer-brand">KivuPass<br><small>Réservation lacustre sécurisée</small></div>
            <div class="site-footer-links"><strong>Navigation</strong><a href="index.html">Accueil</a><a href="enterprise.html">Espace armateur</a></div>
            <div class="site-footer-links"><strong>Nous joindre</strong><div class="site-contact-icons"><a class="site-contact-icon email" href="mailto:michelinekalehezo@gmail.com" aria-label="Email : michelinekalehezo@gmail.com" title="michelinekalehezo@gmail.com"><i class="fas fa-envelope"></i></a><a class="site-contact-icon phone" href="tel:0896883974" aria-label="Téléphone : 0896883974" title="0896883974"><i class="fas fa-phone"></i></a><a class="site-contact-icon whatsapp" href="https://wa.me/243992056878" target="_blank" rel="noopener" aria-label="WhatsApp : 0992056878" title="0992056878"><i class="fab fa-whatsapp"></i></a><a class="site-contact-icon facebook" href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook"><i class="fab fa-facebook-f"></i></a></div></div>
            <small>© ${new Date().getFullYear()}</small>`;
        document.body.appendChild(footer);
    }

    const style = document.createElement('style');
    style.textContent = `:root{--dark:#07152f;--dark-bg:#07152f;--card-bg:#101f3b;--accent:#1677ff;--accent-hover:#00d9e8;--gold:#35e548;--success:#35e548;--success-green:#35e548;--border:#263d64;--border-color:#263d64}.site-navbar{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:10px 5%;background:rgba(7,21,47,.97);border-bottom:1px solid #263d64;position:sticky;top:0;z-index:4000;backdrop-filter:blur(12px)}.site-brand{display:flex;align-items:center;color:#fff;text-decoration:none}.site-logo-image{width:128px;height:42px;object-fit:contain;border-radius:7px}.site-nav-links{display:flex;gap:22px}.site-nav-links a{color:#c8d8f2;text-decoration:none;font-size:14px}.site-nav-links a:hover{color:#00d9e8}.site-footer{display:flex;justify-content:space-between;gap:30px;flex-wrap:wrap;padding:30px 5%;margin-top:50px;background:#050d20;border-top:1px solid #263d64;color:#91a8ca;font-size:13px}.site-footer-brand{color:#35e548;font-weight:700}.site-footer-brand small{color:#91a8ca;font-weight:400}.site-footer-links{display:flex;flex-direction:column;gap:5px}.site-footer-links strong{color:#fff;margin-bottom:8px}.site-footer-links a{color:#91a8ca;text-decoration:none}.site-footer-links a:hover{color:#00d9e8}.site-contact-icons{display:flex;gap:10px}.site-contact-icon{display:grid;place-items:center;width:38px;height:38px;border:1px solid #263d64;border-radius:50%;background:#102447;font-size:16px;transition:transform .2s ease,box-shadow .2s ease}.site-contact-icon:hover,.site-contact-icon:focus-visible{transform:translateY(-3px);color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.35);outline:none}.site-contact-icon.email:hover,.site-contact-icon.email:focus-visible{border-color:#00d9e8;color:#00d9e8}.site-contact-icon.phone:hover,.site-contact-icon.phone:focus-visible{border-color:#1677ff;color:#1677ff}.site-contact-icon.whatsapp:hover,.site-contact-icon.whatsapp:focus-visible{border-color:#35e548;color:#35e548}.site-contact-icon.facebook:hover,.site-contact-icon.facebook:focus-visible{border-color:#2d8cff;color:#2d8cff}@media(max-width:600px){.site-navbar{padding:8px 4%}.site-logo-image{width:108px;height:36px}.site-nav-links{gap:10px}.site-nav-links a{font-size:12px}.site-footer{font-size:12px}}`;
    document.head.appendChild(style);
})();
