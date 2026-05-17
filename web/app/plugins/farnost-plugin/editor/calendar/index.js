/**
 * Kalendár omší a úmyslov — custom admin page (Farnosť → Kalendár omší).
 *
 * Mesačný grid (po–ne), per deň karta s pravidelnými omšami z rozpisu + výnimkami.
 * Klik na omšu otvorí modal editor úmyslu (CPT umysel pre pravidelné, inline meta
 * pre výnimky).
 */

import { createRoot, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, TextControl, TextareaControl, ToggleControl, SelectControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const DAY_KEYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
const DAY_LABELS_SHORT = [
	__( 'Pon', 'farnost-plugin' ),
	__( 'Uto', 'farnost-plugin' ),
	__( 'Str', 'farnost-plugin' ),
	__( 'Štv', 'farnost-plugin' ),
	__( 'Pia', 'farnost-plugin' ),
	__( 'Sob', 'farnost-plugin' ),
	__( 'Ned', 'farnost-plugin' ),
];

const MONTH_NAMES = [
	__( 'Január', 'farnost-plugin' ),  __( 'Február', 'farnost-plugin' ),
	__( 'Marec', 'farnost-plugin' ),   __( 'Apríl', 'farnost-plugin' ),
	__( 'Máj', 'farnost-plugin' ),     __( 'Jún', 'farnost-plugin' ),
	__( 'Júl', 'farnost-plugin' ),     __( 'August', 'farnost-plugin' ),
	__( 'September', 'farnost-plugin' ), __( 'Október', 'farnost-plugin' ),
	__( 'November', 'farnost-plugin' ), __( 'December', 'farnost-plugin' ),
];

// Pozičný fallback pre kostoly bez explicitne nastavenej farby v meta.
// Pravdivý zdroj je teraz `kostol.meta.farnost_color` — táto paleta sa použije
// len keď meta je prázdna (legacy kostoly pred zavedením farby).
const FALLBACK_COLORS = [ '#1e40af', '#15803d', '#b45309', '#7c3aed', '#be185d', '#0e7490' ];

function effectiveKostolColor( meta, idx ) {
	const c = meta?.farnost_color;
	if ( typeof c === 'string' && /^#[A-Fa-f0-9]{3,6}$/.test( c ) ) {
		return c;
	}
	return FALLBACK_COLORS[ idx % FALLBACK_COLORS.length ];
}

function pad2( n ) {
	return String( n ).padStart( 2, '0' );
}

function isoDate( year, monthIdx0, day ) {
	return `${ year }-${ pad2( monthIdx0 + 1 ) }-${ pad2( day ) }`;
}

function dayKey( dateIso ) {
	const d = new Date( dateIso + 'T12:00:00' );
	const js = d.getDay();
	const idx = js === 0 ? 6 : js - 1;
	return DAY_KEYS[ idx ];
}

function parseRozpis( raw ) {
	if ( typeof raw !== 'string' || raw === '' ) {
		return [];
	}
	try {
		const v = JSON.parse( raw );
		return Array.isArray( v ) ? v : [];
	} catch ( e ) {
		return [];
	}
}

function getMonthGrid( year, monthIdx0 ) {
	const firstDay = new Date( year, monthIdx0, 1 );
	const lastDay = new Date( year, monthIdx0 + 1, 0 );
	const daysInMonth = lastDay.getDate();
	const firstWeekday = firstDay.getDay();
	const leadingBlanks = firstWeekday === 0 ? 6 : firstWeekday - 1;
	const cells = [];
	for ( let i = 0; i < leadingBlanks; i++ ) {
		cells.push( null );
	}
	for ( let d = 1; d <= daysInMonth; d++ ) {
		cells.push( d );
	}
	while ( cells.length % 7 !== 0 ) {
		cells.push( null );
	}
	return cells;
}

/**
 * Modal pre úmysel.
 *
 * Pravidelná omša: úmysel žije v samostatnom CPT `umysel`. Save = create alebo update,
 * Odstrániť = DELETE postu (?force=true, lebo trash nedáva tu zmysel).
 *
 * Výnimka: úmysel je inline meta `farnost_umysel` na CPT `omsa_vynimka`. Save = update
 * meta, Odstrániť = vyprázdni meta.
 */
function UmyselModal( { mass, onClose, onSaved } ) {
	const [ text, setText ] = useState( mass.umysel || '' );
	const [ anonymny, setAnonymny ] = useState( !! mass.anonymny );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	// Zmazanie celej mimoriadnej omše (vynimka). Pravidelná (rozpis) sa odtiaľto
	// mazať nedá — rozpis sa upravuje v Farnosť → Kostoly.
	const handleDeleteVynimka = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Naozaj zmazať túto mimoriadnu omšu? Zmizne z kalendára aj z budúcich oznamov.', 'farnost-plugin' ) ) ) {
			return;
		}
		setSaving( true );
		setError( null );
		try {
			await apiFetch( {
				path: `/wp/v2/omsa-vynimky/${ mass.vynimka_id }?force=true`,
				method: 'DELETE',
			} );
			onSaved();
		} catch ( e ) {
			setError( e.message || String( e ) );
			setSaving( false );
		}
	};

	const handleSave = async () => {
		setSaving( true );
		setError( null );
		try {
			if ( mass.source === 'vynimka' ) {
				await apiFetch( {
					path: `/wp/v2/omsa-vynimky/${ mass.vynimka_id }`,
					method: 'POST',
					data: {
						meta: { farnost_umysel: text },
					},
				} );
			} else if ( mass.umysel_id ) {
				if ( text === '' ) {
					// úmysel sa odstraňuje
					await apiFetch( {
						path: `/wp/v2/umysly/${ mass.umysel_id }?force=true`,
						method: 'DELETE',
					} );
				} else {
					await apiFetch( {
						path: `/wp/v2/umysly/${ mass.umysel_id }`,
						method: 'POST',
						data: {
							meta: {
								farnost_text:     text,
								farnost_anonymny: anonymny,
							},
						},
					} );
				}
			} else if ( text !== '' ) {
				// nový úmysel
				await apiFetch( {
					path: '/wp/v2/umysly',
					method: 'POST',
					data: {
						title:  `Úmysel ${ mass.date } ${ mass.time }`,
						status: 'publish',
						meta:   {
							farnost_datum:     mass.date,
							farnost_cas:       mass.time,
							farnost_kostol_id: mass.kostol_id,
							farnost_text:      text,
							farnost_anonymny:  anonymny,
						},
					},
				} );
			}
			onSaved();
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	const formattedDate = new Date( mass.date + 'T12:00:00' ).toLocaleDateString( 'sk-SK', {
		weekday: 'long',
		day: 'numeric',
		month: 'long',
		year: 'numeric',
	} );

	const showAnonymousToggle = mass.source !== 'vynimka';
	const hasExistingUmysel   = mass.source === 'vynimka' ? !! mass.umysel : !! mass.umysel_id;

	return (
		<Modal
			title={ __( 'Úmysel sv. omše', 'farnost-plugin' ) }
			onRequestClose={ onClose }
			style={ { maxWidth: 520 } }
		>
			<div style={ { marginBottom: 12, color: '#374151', fontSize: 13 } }>
				<div><strong>{ formattedDate }</strong> · { mass.time }</div>
				<div style={ { color: '#6b7280', marginTop: 2 } }>
					{ mass.kostol_title }
					{ mass.oznacenie && <span> · { mass.oznacenie }</span> }
					{ mass.source === 'vynimka' && <span> · { __( 'mimoriadna omša', 'farnost-plugin' ) }</span> }
				</div>
			</div>

			<TextareaControl
				label={ __( 'Text úmyslu', 'farnost-plugin' ) }
				value={ text }
				onChange={ setText }
				rows={ 3 }
				placeholder={ __( 'Napr. Za zdravie rodiny / † Mária Nováková', 'farnost-plugin' ) }
				help={ hasExistingUmysel && text === ''
					? __( 'Prázdny text = úmysel sa odstráni.', 'farnost-plugin' )
					: undefined }
				__nextHasNoMarginBottom
			/>

			{ showAnonymousToggle && (
				<div style={ { marginTop: 12 } }>
					<ToggleControl
						label={ __( 'Anonymný úmysel', 'farnost-plugin' ) }
						checked={ anonymny }
						onChange={ setAnonymny }
						help={ __( 'Skryť mená na verejnom webe.', 'farnost-plugin' ) }
						__nextHasNoMarginBottom
					/>
				</div>
			) }

			{ error && (
				<p style={ { color: '#b32d2e', marginTop: 12 } }>
					{ sprintf( __( 'Chyba: %s', 'farnost-plugin' ), error ) }
				</p>
			) }

			{ mass.source === 'rozpis' && (
				<p style={ { marginTop: 12, fontSize: 12, color: '#6b7280' } }>
					{ __( 'Toto je pravidelná omša z rozpisu. Pre úpravu času, označenia alebo zmazanie tejto omše prejdite do Farnosť → Kostoly → rozpis.', 'farnost-plugin' ) }
				</p>
			) }

			<div style={ { display: 'flex', gap: 8, marginTop: 20, justifyContent: 'space-between', alignItems: 'center' } }>
				<div>
					{ mass.source === 'vynimka' && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ handleDeleteVynimka }
							disabled={ saving }
						>
							{ __( 'Zmazať omšu', 'farnost-plugin' ) }
						</Button>
					) }
				</div>
				<div style={ { display: 'flex', gap: 8 } }>
					<Button variant="tertiary" onClick={ onClose } disabled={ saving }>
						{ __( 'Zatvoriť', 'farnost-plugin' ) }
					</Button>
					<Button variant="primary" onClick={ handleSave } disabled={ saving }>
						{ saving ? <Spinner /> : __( 'Uložiť', 'farnost-plugin' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}

/**
 * Modal pre vytvorenie mimoriadnej omše (výnimka) pre konkrétny dátum.
 * POST /wp/v2/omsa-vynimky s meta {datum, cas, kostol_id, oznacenie, umysel}.
 */
function PridatOmsuModal( { date, kostoly, onClose, onSaved } ) {
	const [ cas, setCas ] = useState( '' );
	const [ kostolId, setKostolId ] = useState(
		kostoly.length > 0 ? String( kostoly[ 0 ].id ) : ''
	);
	const [ oznacenie, setOznacenie ] = useState( '' );
	const [ umysel, setUmysel ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleSave = async () => {
		const k = parseInt( kostolId, 10 );
		if ( ! /^\d{2}:\d{2}$/.test( cas ) ) {
			setError( __( 'Zadaj čas vo formáte HH:MM.', 'farnost-plugin' ) );
			return;
		}
		if ( ! k || k <= 0 ) {
			setError( __( 'Zvoľ kostol.', 'farnost-plugin' ) );
			return;
		}
		setSaving( true );
		setError( null );
		try {
			await apiFetch( {
				path: '/wp/v2/omsa-vynimky',
				method: 'POST',
				data: {
					title:  `Výnimka ${ date } ${ cas }`,
					status: 'publish',
					meta:   {
						farnost_datum:     date,
						farnost_cas:       cas,
						farnost_kostol_id: k,
						farnost_oznacenie: oznacenie,
						farnost_umysel:    umysel,
					},
				},
			} );
			onSaved();
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	const formattedDate = new Date( date + 'T12:00:00' ).toLocaleDateString( 'sk-SK', {
		weekday: 'long',
		day: 'numeric',
		month: 'long',
		year: 'numeric',
	} );

	const kostolOptions = kostoly.map( ( k ) => ( {
		value: String( k.id ),
		label: k.title?.rendered || `#${ k.id }`,
	} ) );

	return (
		<Modal
			title={ __( 'Pridať mimoriadnu omšu', 'farnost-plugin' ) }
			onRequestClose={ onClose }
			style={ { maxWidth: 520 } }
		>
			<div style={ { marginBottom: 12, color: '#374151', fontSize: 13 } }>
				<strong>{ formattedDate }</strong>
				<div style={ { color: '#6b7280', marginTop: 2, fontSize: 12 } }>
					{ __( 'Mimoriadna omša sa pridáva nad rámec pravidelného rozpisu (napr. pohreb, sobáš, slávnosť).', 'farnost-plugin' ) }
				</div>
			</div>

			<div style={ { display: 'flex', gap: 12 } }>
				<div style={ { width: 120 } }>
					<TextControl
						label={ __( 'Čas', 'farnost-plugin' ) }
						value={ cas }
						onChange={ setCas }
						placeholder="HH:MM"
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
				<div style={ { flex: 1 } }>
					<SelectControl
						label={ __( 'Kostol', 'farnost-plugin' ) }
						value={ kostolId }
						onChange={ setKostolId }
						options={ kostolOptions }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			</div>

			<div style={ { marginTop: 12 } }>
				<TextControl
					label={ __( 'Označenie omše', 'farnost-plugin' ) }
					value={ oznacenie }
					onChange={ setOznacenie }
					placeholder={ __( 'napr. pohrebná, sobášna, púťová', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>

			<div style={ { marginTop: 12 } }>
				<TextareaControl
					label={ __( 'Úmysel', 'farnost-plugin' ) }
					value={ umysel }
					onChange={ setUmysel }
					rows={ 2 }
					placeholder={ __( 'napr. † p. Nováková', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
				/>
			</div>

			{ error && (
				<p style={ { color: '#b32d2e', marginTop: 12 } }>{ error }</p>
			) }

			<div style={ { display: 'flex', gap: 8, marginTop: 20, justifyContent: 'flex-end' } }>
				<Button variant="tertiary" onClick={ onClose } disabled={ saving }>
					{ __( 'Zatvoriť', 'farnost-plugin' ) }
				</Button>
				<Button variant="primary" onClick={ handleSave } disabled={ saving }>
					{ saving ? <Spinner /> : __( 'Pridať omšu', 'farnost-plugin' ) }
				</Button>
			</div>
		</Modal>
	);
}

function App() {
	const today = new Date();
	const [ year, setYear ] = useState( today.getFullYear() );
	const [ monthIdx0, setMonthIdx0 ] = useState( today.getMonth() );

	const [ kostoly, setKostoly ] = useState( [] );
	const [ vynimky, setVynimky ] = useState( [] );
	const [ umysly, setUmysly ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const [ openMass, setOpenMass ] = useState( null );
	const [ openAddDate, setOpenAddDate ] = useState( null );

	const fetchData = () => {
		setLoading( true );
		return Promise.all( [
			apiFetch( { path: '/wp/v2/kostoly?per_page=100&_fields=id,title,meta' } ),
			apiFetch( { path: '/wp/v2/omsa-vynimky?per_page=100&_fields=id,meta&status=publish' } ),
			apiFetch( { path: '/wp/v2/umysly?per_page=100&_fields=id,meta&status=publish' } ),
		] )
			.then( ( [ k, v, u ] ) => {
				setKostoly( k );
				setVynimky( v );
				setUmysly( u );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || String( err ) );
				setLoading( false );
			} );
	};

	useEffect( () => {
		fetchData();
	}, [] );

	const kostolyById = useMemo( () => {
		const map = {};
		kostoly.forEach( ( k, idx ) => {
			map[ k.id ] = {
				id: k.id,
				title: k.title?.rendered || `#${ k.id }`,
				color: effectiveKostolColor( k.meta, idx ),
				rozpis: parseRozpis( k.meta?.farnost_rozpis ),
			};
		} );
		return map;
	}, [ kostoly ] );

	const grid = getMonthGrid( year, monthIdx0 );

	const dayEvents = useMemo( () => {
		const out = {};
		grid.forEach( ( d ) => {
			if ( ! d ) {
				return;
			}
			const dateIso = isoDate( year, monthIdx0, d );
			const dk = dayKey( dateIso );
			const masses = [];

			Object.values( kostolyById ).forEach( ( k ) => {
				k.rozpis.forEach( ( slot ) => {
					if ( slot.day_of_week === dk && slot.time ) {
						masses.push( {
							date:         dateIso,
							kostol_id:    k.id,
							kostol_title: k.title,
							kostol_color: k.color,
							time:         slot.time,
							oznacenie:    slot.oznacenie || '',
							umysel:       '',
							source:       'rozpis',
						} );
					}
				} );
			} );

			vynimky.forEach( ( v ) => {
				const meta = v.meta || {};
				if ( meta.farnost_datum !== dateIso ) {
					return;
				}
				const k = kostolyById[ meta.farnost_kostol_id ] || {
					id: meta.farnost_kostol_id,
					title: '?',
					color: '#6b7280',
				};
				masses.push( {
					date:         dateIso,
					kostol_id:    k.id,
					kostol_title: k.title,
					kostol_color: k.color,
					time:         meta.farnost_cas || '',
					oznacenie:    meta.farnost_oznacenie || '',
					umysel:       meta.farnost_umysel || '',
					source:       'vynimka',
					vynimka_id:   v.id,
				} );
			} );

			masses.forEach( ( m ) => {
				if ( m.source !== 'rozpis' ) {
					return;
				}
				const u = umysly.find( ( x ) => {
					const xm = x.meta || {};
					return (
						xm.farnost_datum === m.date &&
						xm.farnost_cas === m.time &&
						Number( xm.farnost_kostol_id ) === Number( m.kostol_id )
					);
				} );
				if ( u ) {
					m.umysel    = u.meta?.farnost_text || '';
					m.umysel_id = u.id;
					m.anonymny  = !! u.meta?.farnost_anonymny;
				}
			} );

			masses.sort( ( a, b ) => a.time.localeCompare( b.time ) );
			out[ dateIso ] = masses;
		} );
		return out;
	}, [ grid, year, monthIdx0, kostolyById, vynimky, umysly ] );

	const prevMonth = () => {
		if ( monthIdx0 === 0 ) { setMonthIdx0( 11 ); setYear( year - 1 ); }
		else                   { setMonthIdx0( monthIdx0 - 1 ); }
	};
	const nextMonth = () => {
		if ( monthIdx0 === 11 ) { setMonthIdx0( 0 ); setYear( year + 1 ); }
		else                    { setMonthIdx0( monthIdx0 + 1 ); }
	};
	const todayMonth = () => {
		const n = new Date();
		setYear( n.getFullYear() );
		setMonthIdx0( n.getMonth() );
	};
	const todayIso = isoDate( today.getFullYear(), today.getMonth(), today.getDate() );

	return (
		<div className="farnost-calendar">
			<style>{ `
				.farnost-calendar { max-width: 100%; }
				.farnost-calendar-toolbar { display: flex; align-items: center; gap: 8px; margin: 8px 0 16px; }
				.farnost-calendar-toolbar h2 { margin: 0 16px 0 0; font-size: 18px; }
				.farnost-calendar-legend {
					display: flex; gap: 12px; flex-wrap: wrap;
					margin: 8px 0 16px; font-size: 12px; color: #374151;
					align-items: center;
				}
				.farnost-calendar-legend > span {
					display: inline-flex; align-items: center; gap: 6px;
				}
				.farnost-calendar-legend-swatch {
					display: inline-block; width: 12px; height: 12px;
					border-radius: 2px; border: 1px solid rgba(0,0,0,0.1);
				}
				.farnost-calendar-legend-mimoriadna { color: #6b7280; }
				.farnost-calendar-legend-mimoriadna em {
					padding: 1px 4px; background: #fefce8; border-radius: 2px;
					color: #111827; font-weight: 600;
				}
				.farnost-calendar-grid {
					display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;
					background: #e5e7eb; border: 1px solid #e5e7eb; border-radius: 4px;
				}
				.farnost-calendar-header,
				.farnost-calendar-day {
					background: #fff; padding: 8px; min-height: 100px;
				}
				.farnost-calendar-header {
					text-align: center; font-weight: 600; font-size: 12px;
					background: #f9fafb; min-height: auto; padding: 6px;
				}
				.farnost-calendar-day.empty { background: #f9fafb; }
				.farnost-calendar-day.today { background: #eff6ff; }
				.farnost-calendar-day .day-num {
					font-weight: 600; font-size: 13px; color: #374151;
					display: flex; justify-content: space-between; align-items: center;
					margin-bottom: 6px;
				}
				.farnost-calendar-day.today .day-num { color: #1d4ed8; }
				.farnost-mass {
					font-size: 11px; line-height: 1.3; padding: 3px 5px; margin-bottom: 2px;
					border-left: 3px solid; background: #f9fafb;
					cursor: pointer;
				}
				.farnost-mass:hover { background: #eef2ff; }
				.farnost-mass.is-mimoriadna {
					/* Pravidelná aj mimoriadna majú rovnaký solid border (kostol farba).
					   Mimoriadnu rozlíši italic čas + jemný teplý bg tint — bez chunky
					   dashed border, ktorý browsery pri 3px renderujú ako štvorčeky. */
					background: #fefce8;
				}
				.farnost-mass.is-mimoriadna:hover { background: #fef9c3; }
				.farnost-mass.is-mimoriadna .farnost-mass-time {
					font-style: italic;
				}
				.farnost-mass-time { font-weight: 600; }
				.farnost-mass-um { color: #6b7280; }
				.farnost-mass-um.empty { color: #d1d5db; font-style: italic; }
				.farnost-day-add {
					display: block; width: 100%; margin-top: 4px;
					background: transparent; border: 1px dashed #d1d5db; color: #9ca3af;
					border-radius: 3px; padding: 2px; font-size: 14px; line-height: 1;
					cursor: pointer;
				}
				.farnost-day-add:hover { background: #f3f4f6; color: #374151; border-color: #9ca3af; }
			` }</style>

			<div className="farnost-calendar-toolbar">
				<Button variant="secondary" size="small" onClick={ prevMonth }>‹ { __( 'Predošlý', 'farnost-plugin' ) }</Button>
				<Button variant="secondary" size="small" onClick={ todayMonth }>{ __( 'Dnes', 'farnost-plugin' ) }</Button>
				<Button variant="secondary" size="small" onClick={ nextMonth }>{ __( 'Nasledujúci', 'farnost-plugin' ) } ›</Button>
				<h2>{ MONTH_NAMES[ monthIdx0 ] } { year }</h2>
			</div>

			{ Object.values( kostolyById ).length > 0 && (
				<div className="farnost-calendar-legend">
					{ Object.values( kostolyById ).map( ( k ) => (
						<span key={ k.id }>
							<span
								className="farnost-calendar-legend-swatch"
								style={ { background: k.color } }
							/>
							{ k.title }
						</span>
					) ) }
					<span className="farnost-calendar-legend-mimoriadna">
						<em aria-hidden="true">14:00</em>
						{ __( '= mimoriadna omša (pohreb, sobáš, …)', 'farnost-plugin' ) }
					</span>
				</div>
			) }

			{ loading && <p>{ __( 'Načítavam…', 'farnost-plugin' ) }</p> }
			{ error && <p style={ { color: '#b32d2e' } }>{ sprintf( __( 'Chyba: %s', 'farnost-plugin' ), error ) }</p> }

			{ ! loading && ! error && (
				<div className="farnost-calendar-grid">
					{ DAY_LABELS_SHORT.map( ( label ) => (
						<div key={ label } className="farnost-calendar-header">{ label }</div>
					) ) }
					{ grid.map( ( d, idx ) => {
						if ( ! d ) {
							return <div key={ idx } className="farnost-calendar-day empty"></div>;
						}
						const dateIso = isoDate( year, monthIdx0, d );
						const masses = dayEvents[ dateIso ] || [];
						const isToday = dateIso === todayIso;
						return (
							<div key={ idx } className={ `farnost-calendar-day${ isToday ? ' today' : '' }` }>
								<div className="day-num">
									<span>{ d }</span>
								</div>
								{ masses.map( ( m, i ) => (
									<div
										key={ i }
										className={ `farnost-mass${ m.source === 'vynimka' ? ' is-mimoriadna' : '' }` }
										style={ { borderColor: m.kostol_color, color: '#111827' } }
										title={ `${ m.kostol_title }${ m.oznacenie ? ' · ' + m.oznacenie : '' }${ m.source === 'vynimka' ? ' (mimoriadna)' : '' }` }
										onClick={ () => setOpenMass( m ) }
									>
										<span className="farnost-mass-time">{ m.time }</span>
										{ m.oznacenie && <span> · { m.oznacenie }</span> }
										<div className={ `farnost-mass-um${ m.umysel ? '' : ' empty' }` }>
											{ m.umysel || ( m.source === 'rozpis' ? __( 'voľný úmysel', 'farnost-plugin' ) : '' ) }
										</div>
									</div>
								) ) }
								<button
									type="button"
									className="farnost-day-add"
									onClick={ () => setOpenAddDate( dateIso ) }
									title={ __( 'Pridať mimoriadnu omšu v tento deň', 'farnost-plugin' ) }
									aria-label={ __( 'Pridať omšu', 'farnost-plugin' ) }
								>
									+
								</button>
							</div>
						);
					} ) }
				</div>
			) }

			{ openMass && (
				<UmyselModal
					mass={ openMass }
					onClose={ () => setOpenMass( null ) }
					onSaved={ () => {
						setOpenMass( null );
						fetchData();
					} }
				/>
			) }

			{ openAddDate && (
				<PridatOmsuModal
					date={ openAddDate }
					kostoly={ kostoly }
					onClose={ () => setOpenAddDate( null ) }
					onSaved={ () => {
						setOpenAddDate( null );
						fetchData();
					} }
				/>
			) }
		</div>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	const mount = document.getElementById( 'farnost-calendar-root' );
	if ( mount ) {
		createRoot( mount ).render( <App /> );
	}
} );
