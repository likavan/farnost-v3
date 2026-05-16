/**
 * Kalendár omší a úmyslov — custom admin page (Farnosť → Kalendár omší).
 *
 * Mesačný grid (po–ne), per deň karta s pravidelnými omšami z rozpisu + výnimkami.
 * Tento prvý prírastok: read-only zobrazenie. Interakcia (modal pre úmysel / pridanie
 * mimoriadnej omše) príde v nasledujúcich commitoch.
 */

import { createRoot, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
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

// Paleta pre rozlíšenie kostolov (cyklicky).
const KOSTOL_COLORS = [ '#1e40af', '#15803d', '#b45309', '#7c3aed', '#be185d', '#0e7490' ];

function pad2( n ) {
	return String( n ).padStart( 2, '0' );
}

function isoDate( year, monthIdx0, day ) {
	return `${ year }-${ pad2( monthIdx0 + 1 ) }-${ pad2( day ) }`;
}

function dayKey( dateIso ) {
	// JS getDay: 0=Sun..6=Sat. Premapujeme na náš formát mon..sun.
	const d = new Date( dateIso + 'T12:00:00' );
	const js = d.getDay(); // 0..6
	const idx = js === 0 ? 6 : js - 1; // 0=mon..6=sun
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

	// Posun na pondelok — JS getDay: 0=Ne..6=So, my potrebujeme týždeň Po..Ne.
	const firstWeekday = firstDay.getDay(); // 0..6
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

function App() {
	const today = new Date();
	const [ year, setYear ] = useState( today.getFullYear() );
	const [ monthIdx0, setMonthIdx0 ] = useState( today.getMonth() );

	const [ kostoly, setKostoly ] = useState( [] );
	const [ vynimky, setVynimky ] = useState( [] );
	const [ umysly, setUmysly ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		Promise.all( [
			apiFetch( { path: '/wp/v2/kostoly?per_page=100&_fields=id,title,meta' } ),
			apiFetch( { path: '/wp/v2/omsa-vynimky?per_page=100&_fields=id,meta&status=publish' } ),
			apiFetch( { path: '/wp/v2/umysly?per_page=100&_fields=id,meta&status=publish' } ),
		] )
			.then( ( [ k, v, u ] ) => {
				if ( cancelled ) {
					return;
				}
				setKostoly( k );
				setVynimky( v );
				setUmysly( u );
				setLoading( false );
			} )
			.catch( ( err ) => {
				if ( cancelled ) {
					return;
				}
				setError( err.message || String( err ) );
				setLoading( false );
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const kostolyById = useMemo( () => {
		const map = {};
		kostoly.forEach( ( k, idx ) => {
			map[ k.id ] = {
				id: k.id,
				title: k.title?.rendered || `#${ k.id }`,
				color: KOSTOL_COLORS[ idx % KOSTOL_COLORS.length ],
				rozpis: parseRozpis( k.meta?.farnost_rozpis ),
			};
		} );
		return map;
	}, [ kostoly ] );

	const grid = getMonthGrid( year, monthIdx0 );

	// Pre každý deň v aktuálnom mesiaci vyrátaj zoznam omší (zlúčenie rozpis + výnimky)
	// + napárovanie úmyslov na omšu cez (dátum, čas, kostol_id).
	const dayEvents = useMemo( () => {
		const out = {};
		grid.forEach( ( d ) => {
			if ( ! d ) {
				return;
			}
			const dateIso = isoDate( year, monthIdx0, d );
			const dk = dayKey( dateIso );
			const masses = [];

			// pravidelné z rozpisu
			Object.values( kostolyById ).forEach( ( k ) => {
				k.rozpis.forEach( ( slot ) => {
					if ( slot.day_of_week === dk && slot.time ) {
						masses.push( {
							kostol_id: k.id,
							kostol_title: k.title,
							kostol_color: k.color,
							time: slot.time,
							oznacenie: slot.oznacenie || '',
							umysel: '',
							source: 'rozpis',
						} );
					}
				} );
			} );

			// výnimky pre tento deň
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
					kostol_id: k.id,
					kostol_title: k.title,
					kostol_color: k.color,
					time: meta.farnost_cas || '',
					oznacenie: meta.farnost_oznacenie || '',
					umysel: meta.farnost_umysel || '',
					source: 'vynimka',
				} );
			} );

			// napáruj úmysly z CPT na omše (dátum + čas + kostol_id) — výnimky majú
			// úmysel inline, takže ich nepárujeme, len pravidelné slot-y dopĺňame.
			masses.forEach( ( m ) => {
				if ( m.source !== 'rozpis' ) {
					return;
				}
				const u = umysly.find( ( x ) => {
					const xm = x.meta || {};
					return (
						xm.farnost_datum === dateIso &&
						xm.farnost_cas === m.time &&
						Number( xm.farnost_kostol_id ) === Number( m.kostol_id )
					);
				} );
				if ( u ) {
					m.umysel = u.meta?.farnost_text || '';
					m.umysel_id = u.id;
					m.anonymny = !! u.meta?.farnost_anonymny;
				}
			} );

			masses.sort( ( a, b ) => a.time.localeCompare( b.time ) );
			out[ dateIso ] = masses;
		} );
		return out;
	}, [ grid, year, monthIdx0, kostolyById, vynimky, umysly ] );

	const prevMonth = () => {
		if ( monthIdx0 === 0 ) {
			setMonthIdx0( 11 );
			setYear( year - 1 );
		} else {
			setMonthIdx0( monthIdx0 - 1 );
		}
	};

	const nextMonth = () => {
		if ( monthIdx0 === 11 ) {
			setMonthIdx0( 0 );
			setYear( year + 1 );
		} else {
			setMonthIdx0( monthIdx0 + 1 );
		}
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
				.farnost-calendar-legend { display: flex; gap: 12px; flex-wrap: wrap; margin: 8px 0 16px; font-size: 12px; }
				.farnost-calendar-legend span { display: inline-flex; align-items: center; gap: 4px; }
				.farnost-calendar-legend span::before {
					content: ''; display: inline-block; width: 10px; height: 10px; border-radius: 2px;
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
					border-left: 3px solid; border-radius: 2px; background: #f9fafb;
				}
				.farnost-mass-time { font-weight: 600; }
				.farnost-mass-um { color: #6b7280; }
				.farnost-mass-um.empty { color: #d1d5db; font-style: italic; }
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
						<span key={ k.id } style={ { '--c': k.color } }>
							<span style={ { background: k.color } }></span>
							{ k.title }
						</span>
					) ) }
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
										className="farnost-mass"
										style={ { borderColor: m.kostol_color, color: '#111827' } }
										title={ `${ m.kostol_title }${ m.oznacenie ? ' · ' + m.oznacenie : '' }${ m.source === 'vynimka' ? ' (mimoriadna)' : '' }` }
									>
										<span className="farnost-mass-time">{ m.time }</span>
										{ m.oznacenie && <span> · { m.oznacenie }</span> }
										<div className={ `farnost-mass-um${ m.umysel ? '' : ' empty' }` }>
											{ m.umysel || ( m.source === 'rozpis' ? __( 'voľný úmysel', 'farnost-plugin' ) : '' ) }
										</div>
									</div>
								) ) }
							</div>
						);
					} ) }
				</div>
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
