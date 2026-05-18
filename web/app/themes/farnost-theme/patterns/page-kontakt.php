<?php
/**
 * Title: Kontakt (stránka)
 * Slug: farnost-theme/page-kontakt
 * Categories: farnost-pages
 * Description: Kontaktná stránka — farský úrad, úradné hodiny, formulár.
 */
?>
<!-- wp:html -->
<header class="farnost-page-header">
	<div class="farnost-page-eyebrow">Kontakt</div>
	<h1 class="farnost-page-title">Spojte sa s nami</h1>
	<p class="farnost-page-lead">Farský úrad je vám k dispozícii v úradných hodinách. Mimo nich vás prosíme kontaktovať telefonicky alebo cez nasledujúci formulár.</p>
</header>
<div class="farnost-kontakt-grid">
	<div>
		<h2 class="farnost-block-title">Farský úrad</h2>
		<dl class="farnost-kontakt-dl">
			<div><dt>Adresa</dt><dd>Námestie sv. Martina 7, 974 01 Banská Bystrica</dd></div>
			<div><dt>Telefón</dt><dd><a href="tel:+421484152233">+421 48 415 22 33</a></dd></div>
			<div><dt>E-mail</dt><dd><a href="mailto:farnost.martin@centrum.sk">farnost.martin@centrum.sk</a></dd></div>
			<div><dt>IČO</dt><dd>31 932 411</dd></div>
		</dl>
		<h2 class="farnost-block-title" style="margin-top:28px;">Úradné hodiny</h2>
		<dl class="farnost-kontakt-dl">
			<div><dt>Pondelok</dt><dd>9:00 – 11:00</dd></div>
			<div><dt>Streda</dt><dd>15:00 – 17:00</dd></div>
			<div><dt>Piatok</dt><dd>9:00 – 11:00</dd></div>
			<div><dt>Iné</dt><dd class="muted">po dohode</dd></div>
		</dl>
	</div>
	<form class="farnost-kontakt-form" method="post" action="#">
		<h2 class="farnost-block-title">Napíšte nám</h2>
		<label>Meno a priezvisko<input type="text" name="meno" required></label>
		<label>E-mail<input type="email" name="email" required></label>
		<label>Vec<input type="text" name="vec"></label>
		<label>Správa<textarea name="sprava" rows="6" required></textarea></label>
		<button type="submit" class="farnost-btn-primary">Odoslať</button>
	</form>
</div>
<!-- /wp:html -->
