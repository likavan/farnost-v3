/**
 * Setup wizard pre Farnosť Online.
 *
 * Plnoobrazovkový multistep sprievodca. Vyrenderuje sa mimo WP admin chrome
 * (server-side: src/Admin/WizardPage.php → renderStandalone).
 *
 * Kroky:
 *   1. Identita (názov povinné, patrocínium, diecéza)
 *   2. Kontakt (adresa, telefón, email, IBAN)
 *   3. Prvý kostol (názov povinné, adresa)
 *   4. Režim (plný vs lite)
 *
 * Pri Dokončiť: PUT /wp/v2/settings (farnost_settings + setup.completed=true),
 * POST /wp/v2/kostoly so základným kostolom. Po úspechu redirect na Farnosť dashboard.
 */

import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl, RadioControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const TOTAL_STEPS = 4;

function App({ homeUrl }) {
	const [ step, setStep ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	// Štruktúrované polia (predvyplnia sa z existujúcich settings v useEffect)
	const [ identita, setIdentita ] = useState( {
		nazov: '',
		patrocinium: '',
		dioceza: '',
	} );
	const [ kontakt, setKontakt ] = useState( {
		adresa: '',
		telefon: '',
		email: '',
		iban: '',
	} );
	const [ kostol, setKostol ] = useState( {
		id: 0,           // 0 = nový, > 0 = update existujúceho
		nazov: '',
		adresa: '',
	} );
	const [ rezim, setRezim ] = useState( 'full' );

	useEffect( () => {
		let cancelled = false;
		Promise.all( [
			apiFetch( { path: '/wp/v2/settings' } ),
			apiFetch( { path: '/wp/v2/kostoly?per_page=100&_fields=id,title,meta&status=publish' } ),
		] )
			.then( ( [ settings, kostoly ] ) => {
				if ( cancelled ) return;
				const fs = settings.farnost_settings || {};

				setIdentita( {
					nazov:       fs.identita?.nazov || '',
					patrocinium: fs.identita?.patrocinium || '',
					dioceza:     fs.identita?.dioceza || '',
				} );

				const firstPhone = fs.kontakt?.telefony?.[ 0 ]?.cislo || '';
				const firstEmail = fs.kontakt?.emaily?.[ 0 ]?.adresa || '';
				setKontakt( {
					adresa:  fs.kontakt?.adresa || '',
					telefon: firstPhone,
					email:   firstEmail,
					iban:    fs.financie?.iban || '',
				} );

				// Pre kostol uprednostníme ten s je_hlavny = true, inak prvý.
				const hlavny = kostoly.find( ( k ) => k.meta?.farnost_je_hlavny ) || kostoly[ 0 ];
				if ( hlavny ) {
					setKostol( {
						id:     hlavny.id,
						nazov:  hlavny.title?.rendered || '',
						adresa: hlavny.meta?.farnost_adresa || '',
					} );
				}

				// Režim: ak je oznamy alebo úmysly aspoň jedno vypnuté, považuj za lite.
				const m = fs.moduly || {};
				if ( m.oznamy_zapnute === false || m.umysly_zapnute === false ) {
					setRezim( 'lite' );
				} else {
					setRezim( 'full' );
				}

				setLoading( false );
			} )
			.catch( ( e ) => {
				if ( cancelled ) return;
				// Pri chybe nezablokujeme wizard — necháme prázdne polia.
				setError( e.message || String( e ) );
				setLoading( false );
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const canProceedFromStep = ( s ) => {
		if ( s === 1 ) return identita.nazov.trim() !== '';
		if ( s === 3 ) return kostol.nazov.trim() !== '';
		return true;
	};

	const next = () => {
		if ( ! canProceedFromStep( step ) ) {
			setError( __( 'Vyplň povinné pole.', 'farnost-plugin' ) );
			return;
		}
		setError( null );
		setStep( step + 1 );
	};

	const back = () => {
		setError( null );
		setStep( step - 1 );
	};

	const finish = async () => {
		if ( ! canProceedFromStep( 3 ) ) {
			setError( __( 'Vyplň povinné pole.', 'farnost-plugin' ) );
			return;
		}
		setSaving( true );
		setError( null );
		try {
			// 1) Načítaj aktuálne settings (defaults + ev. existujúce hodnoty)
			const current = await apiFetch( { path: '/wp/v2/settings' } );
			const fs = current.farnost_settings || {};
			const next = {
				...fs,
				identita: {
					...( fs.identita || {} ),
					nazov:       identita.nazov,
					patrocinium: identita.patrocinium,
					dioceza:     identita.dioceza,
				},
				kontakt: {
					...( fs.kontakt || {} ),
					adresa:   kontakt.adresa,
					telefony: kontakt.telefon ? [ { popis: '', cislo: kontakt.telefon } ] : ( fs.kontakt?.telefony || [] ),
					emaily:   kontakt.email   ? [ { popis: '', adresa: kontakt.email } ] : ( fs.kontakt?.emaily || [] ),
				},
				financie: {
					...( fs.financie || {} ),
					iban: kontakt.iban,
				},
				moduly: {
					...( fs.moduly || {} ),
					oznamy_zapnute:      rezim === 'full',
					umysly_zapnute:      rezim === 'full',
					rozpis_omsi_zapnuty: true, // rozpis necháme zapnutý aj v lite (sidebar widget)
				},
				setup: {
					completed:    true,
					completed_at: new Date().toISOString(),
				},
			};

			await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { farnost_settings: next },
			} );

			// 2) Vytvor alebo aktualizuj hlavný kostol
			if ( kostol.id > 0 ) {
				await apiFetch( {
					path: `/wp/v2/kostoly/${ kostol.id }`,
					method: 'POST',
					data: {
						title:  kostol.nazov,
						status: 'publish',
						meta: {
							farnost_adresa:    kostol.adresa,
							farnost_je_hlavny: true,
						},
					},
				} );
			} else {
				await apiFetch( {
					path: '/wp/v2/kostoly',
					method: 'POST',
					data: {
						title:  kostol.nazov,
						status: 'publish',
						meta: {
							farnost_adresa:    kostol.adresa,
							farnost_je_hlavny: true,
						},
					},
				} );
			}

			// 3) Redirect na Farnosť dashboard
			window.location.href = homeUrl;
		} catch ( e ) {
			setError( e.message || String( e ) );
			setSaving( false );
		}
	};

	return (
		<div className="farnost-wizard">
			<style>{ `
				.farnost-wizard {
					max-width: 600px; margin: 60px auto; padding: 32px 40px;
					background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
				}
				.farnost-wizard-progress {
					display: flex; gap: 6px; margin-bottom: 28px;
				}
				.farnost-wizard-progress span {
					flex: 1; height: 4px; border-radius: 2px; background: #e5e7eb;
				}
				.farnost-wizard-progress span.active { background: #1e40af; }
				.farnost-wizard h1 {
					font-size: 24px; margin: 0 0 8px; color: #111827;
				}
				.farnost-wizard-step {
					font-size: 12px; color: #6b7280; margin-bottom: 24px;
					text-transform: uppercase; letter-spacing: 0.5px;
				}
				.farnost-wizard-field { margin-bottom: 16px; }
				.farnost-wizard-required::after {
					content: ' *'; color: #b32d2e;
				}
				.farnost-wizard-help {
					font-size: 12px; color: #6b7280; margin-top: 4px;
				}
				.farnost-wizard-buttons {
					display: flex; justify-content: space-between; align-items: center;
					margin-top: 32px; padding-top: 20px; border-top: 1px solid #e5e7eb;
				}
				.farnost-wizard-error {
					padding: 10px 14px; background: #fef2f2; border-left: 3px solid #b32d2e;
					color: #991b1b; margin-bottom: 16px; border-radius: 3px; font-size: 13px;
				}
			` }</style>

			<div className="farnost-wizard-progress">
				{ Array.from( { length: TOTAL_STEPS } ).map( ( _, i ) => (
					<span key={ i } className={ i + 1 <= step ? 'active' : '' } />
				) ) }
			</div>

			<div className="farnost-wizard-step">
				{ sprintf( __( 'Krok %1$d z %2$d', 'farnost-plugin' ), step, TOTAL_STEPS ) }
			</div>

			{ error && (
				<div className="farnost-wizard-error">{ error }</div>
			) }

			{ loading ? (
				<div style={ { textAlign: 'center', padding: '40px 0' } }>
					<Spinner />
					<p style={ { color: '#6b7280', marginTop: 12 } }>
						{ __( 'Načítavam vaše uložené nastavenia…', 'farnost-plugin' ) }
					</p>
				</div>
			) : (
				<>
					{ step === 1 && <StepIdentita value={ identita } onChange={ setIdentita } /> }
					{ step === 2 && <StepKontakt value={ kontakt } onChange={ setKontakt } /> }
					{ step === 3 && <StepKostol value={ kostol } onChange={ setKostol } /> }
					{ step === 4 && <StepRezim value={ rezim } onChange={ setRezim } /> }
				</>
			) }

			<div className="farnost-wizard-buttons">
				<Button
					variant="tertiary"
					onClick={ back }
					disabled={ step === 1 || saving }
				>
					{ __( '‹ Späť', 'farnost-plugin' ) }
				</Button>
				{ step < TOTAL_STEPS ? (
					<Button variant="primary" onClick={ next } disabled={ saving }>
						{ __( 'Pokračovať ›', 'farnost-plugin' ) }
					</Button>
				) : (
					<Button variant="primary" onClick={ finish } disabled={ saving }>
						{ saving ? <Spinner /> : __( 'Dokončiť nastavenie', 'farnost-plugin' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

function StepIdentita( { value, onChange } ) {
	return (
		<>
			<h1>{ __( 'Identita farnosti', 'farnost-plugin' ) }</h1>
			<div className="farnost-wizard-field">
				<TextControl
					label={ <span className="farnost-wizard-required">{ __( 'Názov farnosti', 'farnost-plugin' ) }</span> }
					value={ value.nazov }
					onChange={ ( v ) => onChange( { ...value, nazov: v } ) }
					placeholder={ __( 'Napr. Rímskokatolícka farnosť sv. Martina', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="farnost-wizard-field">
				<TextControl
					label={ __( 'Patrón farnosti', 'farnost-plugin' ) }
					value={ value.patrocinium }
					onChange={ ( v ) => onChange( { ...value, patrocinium: v } ) }
					placeholder={ __( 'Napr. sv. Martin z Tours', 'farnost-plugin' ) }
					help={ __( 'Voliteľné — meno svätca/titulu farnosti, použije sa v hlavičke a SEO.', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="farnost-wizard-field">
				<TextControl
					label={ __( 'Diecéza', 'farnost-plugin' ) }
					value={ value.dioceza }
					onChange={ ( v ) => onChange( { ...value, dioceza: v } ) }
					placeholder={ __( 'Napr. Trnavská arcidiecéza', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
		</>
	);
}

function StepKontakt( { value, onChange } ) {
	return (
		<>
			<h1>{ __( 'Kontakt', 'farnost-plugin' ) }</h1>
			<p className="farnost-wizard-help" style={ { marginBottom: 16 } }>
				{ __( 'Všetko voliteľné — môžete doplniť neskôr v Nastaveniach.', 'farnost-plugin' ) }
			</p>
			<div className="farnost-wizard-field">
				<TextControl
					label={ __( 'Adresa', 'farnost-plugin' ) }
					value={ value.adresa }
					onChange={ ( v ) => onChange( { ...value, adresa: v } ) }
					placeholder="Hlavná 1, 917 01 Trnava"
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="farnost-wizard-field">
				<TextControl
					label={ __( 'Telefón', 'farnost-plugin' ) }
					value={ value.telefon }
					onChange={ ( v ) => onChange( { ...value, telefon: v } ) }
					placeholder="+421 905 ..."
					help={ __( 'Ďalšie čísla pridáte v Nastaveniach.', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="farnost-wizard-field">
				<TextControl
					label={ __( 'E-mail', 'farnost-plugin' ) }
					type="email"
					value={ value.email }
					onChange={ ( v ) => onChange( { ...value, email: v } ) }
					placeholder="farnost@example.sk"
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="farnost-wizard-field">
				<TextControl
					label="IBAN"
					value={ value.iban }
					onChange={ ( v ) => onChange( { ...value, iban: v } ) }
					placeholder="SK00 0000 0000 0000 0000 0000"
					help={ __( 'Použije sa v sidebare a v päte webu pre milodary.', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
		</>
	);
}

function StepKostol( { value, onChange } ) {
	return (
		<>
			<h1>{ __( 'Prvý kostol', 'farnost-plugin' ) }</h1>
			<p className="farnost-wizard-help" style={ { marginBottom: 16 } }>
				{ __( 'Hlavný kostol farnosti. Rozpis omší a ďalšie filiálky pridáte neskôr cez Farnosť → Kostoly.', 'farnost-plugin' ) }
			</p>
			<div className="farnost-wizard-field">
				<TextControl
					label={ <span className="farnost-wizard-required">{ __( 'Názov kostola', 'farnost-plugin' ) }</span> }
					value={ value.nazov }
					onChange={ ( v ) => onChange( { ...value, nazov: v } ) }
					placeholder={ __( 'Napr. Farský kostol sv. Martina', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div className="farnost-wizard-field">
				<TextareaControl
					label={ __( 'Adresa', 'farnost-plugin' ) }
					value={ value.adresa }
					onChange={ ( v ) => onChange( { ...value, adresa: v } ) }
					rows={ 2 }
					placeholder="Hlavná 1, 917 01 Trnava"
					__nextHasNoMarginBottom
				/>
			</div>
		</>
	);
}

function StepRezim( { value, onChange } ) {
	return (
		<>
			<h1>{ __( 'Režim oznamov', 'farnost-plugin' ) }</h1>
			<p className="farnost-wizard-help" style={ { marginBottom: 20 } }>
				{ __( 'Ako budete spravovať týždenné oznamy?', 'farnost-plugin' ) }
			</p>
			<RadioControl
				selected={ value }
				options={ [
					{
						label: __( 'Plný — štruktúrovaný editor s automatickým rozpisom a úmyslami (odporúčané)', 'farnost-plugin' ),
						value: 'full',
					},
					{
						label: __( 'Lite — nahrávam oznamy ako PDF z Wordu, žiadny štruktúrovaný editor', 'farnost-plugin' ),
						value: 'lite',
					},
				] }
				onChange={ onChange }
			/>
			<p className="farnost-wizard-help" style={ { marginTop: 16 } }>
				{ __( 'Voľbu môžete kedykoľvek zmeniť v Nastaveniach → Moduly.', 'farnost-plugin' ) }
			</p>
		</>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	const mount = document.getElementById( 'farnost-wizard-root' );
	if ( ! mount ) return;
	const homeUrl = mount.getAttribute( 'data-home-url' ) || '';
	createRoot( mount ).render( <App homeUrl={ homeUrl } /> );
} );
