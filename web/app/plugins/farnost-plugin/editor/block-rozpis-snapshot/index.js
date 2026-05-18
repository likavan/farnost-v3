/**
 * Block `farnost/rozpis-snapshot` — snapshot rozpisu omší v ozname.
 *
 * Atribúty:
 *   - tyzdenOd, tyzdenDo: 'YYYY-MM-DD'
 *   - dni: pole 7 položiek (nový shape, per kostol)
 *       { date, dayKey, sviatok,
 *         kostoly: [{ id, title, color, omse: [{ cas, oznacenie, umysel, source }] }] }
 *     Legacy shape (staršie oznamy): { ..., omse: [{ kostol_title, time, oznacenie, umysel, source }] }
 *     — pri editácii ho znormalizujeme do nového shape.
 *
 * Edit UI: každá bunka (čas / označenie / úmysel) je click-to-edit; klik na text
 * prepne na <input>, blur alebo Enter ulož, Escape zruš. Tlačidlá per deň/kostol:
 * pridať omšu, odstrániť konkrétnu omšu.
 *
 * Dynamic block — JS save() vracia null, frontend rendruje PHP (RozpisSnapshot.php).
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const DAY_LABELS = {
	mon: __( 'Pondelok', 'farnost-plugin' ),
	tue: __( 'Utorok', 'farnost-plugin' ),
	wed: __( 'Streda', 'farnost-plugin' ),
	thu: __( 'Štvrtok', 'farnost-plugin' ),
	fri: __( 'Piatok', 'farnost-plugin' ),
	sat: __( 'Sobota', 'farnost-plugin' ),
	sun: __( 'Nedeľa', 'farnost-plugin' ),
};

function formatShortDate( iso ) {
	if ( ! iso ) return '';
	const d = new Date( iso + 'T12:00:00' );
	return d.toLocaleDateString( 'sk-SK', { day: 'numeric', month: 'numeric' } );
}

/**
 * Vráti deň v normalizovanom shape `kostoly[]` (nový formát).
 * Ak day má legacy `omse[]` flat shape, re-groupne podľa `kostol_title`.
 * Ak chýbajú obe pole, vráti `kostoly: []`.
 *
 * Tým editor vie pracovať jednotne — vždy iteruje `day.kostoly[]`. Legacy
 * snapshots (uložené pred prerodom shape) sa za behu prevedú do nového
 * formátu, takže edit/save funguje bez migrácie content-u v databáze.
 */
function normalizeDay( day ) {
	if ( Array.isArray( day.kostoly ) ) {
		return day;
	}
	const omse = Array.isArray( day.omse ) ? day.omse : [];
	if ( omse.length === 0 ) {
		return { ...day, kostoly: [] };
	}
	const byTitle = new Map();
	const order = [];
	for ( const m of omse ) {
		const title = String( m.kostol_title || '' );
		if ( ! byTitle.has( title ) ) {
			byTitle.set( title, [] );
			order.push( title );
		}
		byTitle.get( title ).push( {
			cas:       String( m.cas || m.time || '' ),
			oznacenie: String( m.oznacenie || '' ),
			umysel:    String( m.umysel || '' ),
			source:    String( m.source || 'rozpis' ),
		} );
	}
	const kostoly = order.map( ( title ) => ( {
		id:    0,
		title,
		color: '',
		omse:  byTitle.get( title ),
	} ) );
	return { ...day, kostoly };
}

/**
 * Inline click-to-edit text field. Display mode = plain span; klik / focus prepne
 * na <input>. Blur a Enter commit, Escape revert.
 */
function InlineEdit( { value, onChange, placeholder, inputType = 'text', style } ) {
	const [ editing, setEditing ] = useState( false );
	const [ draft, setDraft ] = useState( value );
	const inputRef = useRef( null );

	useEffect( () => {
		if ( ! editing ) {
			setDraft( value );
		}
	}, [ value, editing ] );

	useEffect( () => {
		if ( editing && inputRef.current ) {
			inputRef.current.focus();
			inputRef.current.select?.();
		}
	}, [ editing ] );

	const commit = () => {
		setEditing( false );
		if ( draft !== value ) {
			onChange( draft );
		}
	};

	const cancel = () => {
		setDraft( value );
		setEditing( false );
	};

	if ( editing ) {
		return (
			<input
				ref={ inputRef }
				type={ inputType }
				value={ draft }
				onChange={ ( e ) => setDraft( e.target.value ) }
				onBlur={ commit }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) {
						commit();
					} else if ( e.key === 'Escape' ) {
						cancel();
					}
				} }
				className="farnost-rozpis-snapshot__input"
				style={ style }
			/>
		);
	}

	const isEmpty = value === '' || value == null;
	return (
		<button
			type="button"
			onClick={ () => setEditing( true ) }
			onFocus={ () => setEditing( true ) }
			className="farnost-rozpis-snapshot__inline"
			style={ {
				...style,
				fontStyle: isEmpty ? 'italic' : 'normal',
				color: isEmpty ? '#9ca3af' : 'inherit',
			} }
		>
			{ isEmpty ? placeholder : value }
		</button>
	);
}

function DayCardEdit( { day, dayIdx, updateMass, addMass, removeMass } ) {
	const label = DAY_LABELS[ day.dayKey ] || day.dayKey;
	const kostoly = Array.isArray( day.kostoly ) ? day.kostoly : [];

	const totalOmse = kostoly.reduce(
		( acc, k ) => acc + ( Array.isArray( k.omse ) ? k.omse.length : 0 ),
		0
	);

	return (
		<div className="farnost-rozpis-snapshot__card">
			<div className="farnost-rozpis-snapshot__header">
				<strong>{ label }</strong>
				{ day.date && (
					<span className="farnost-rozpis-snapshot__date">{ formatShortDate( day.date ) }</span>
				) }
				{ day.sviatok && (
					<div className="farnost-rozpis-snapshot__sviatok">{ day.sviatok }</div>
				) }
			</div>

			{ totalOmse === 0 && kostoly.length === 0 ? (
				<div className="farnost-rozpis-snapshot__empty">
					{ __( 'Sv. omša nie je', 'farnost-plugin' ) }
				</div>
			) : (
				kostoly.map( ( k, ki ) => {
					const omse = Array.isArray( k.omse ) ? k.omse : [];
					const borderColor = k.color || '#e5e7eb';
					return (
						<div
							key={ ki }
							className="farnost-rozpis-snapshot__kostol"
							style={ { borderLeftColor: borderColor } }
						>
							{ k.title && (
								<div className="farnost-rozpis-snapshot__kostol-title">{ k.title }</div>
							) }

							{ omse.length === 0 ? (
								<div className="farnost-rozpis-snapshot__empty">
									{ __( 'Sv. omša nie je', 'farnost-plugin' ) }
								</div>
							) : (
								<ul className="farnost-rozpis-snapshot__list">
									{ omse.map( ( m, i ) => (
										<li key={ i } className="farnost-rozpis-snapshot__row">
											<div className="farnost-rozpis-snapshot__row-line">
												<InlineEdit
													value={ m.cas || '' }
													onChange={ ( v ) => updateMass( dayIdx, ki, i, 'cas', v ) }
													placeholder="HH:MM"
													style={ { width: 56, fontWeight: 600 } }
												/>
												<InlineEdit
													value={ m.oznacenie || '' }
													onChange={ ( v ) => updateMass( dayIdx, ki, i, 'oznacenie', v ) }
													placeholder={ __( 'označenie', 'farnost-plugin' ) }
													style={ { flex: 1, fontSize: 12, color: '#6b7280' } }
												/>
												<Button
													isDestructive
													variant="tertiary"
													size="small"
													onClick={ () => removeMass( dayIdx, ki, i ) }
													label={ __( 'Odstrániť omšu', 'farnost-plugin' ) }
													showTooltip
												>
													✕
												</Button>
											</div>
											<InlineEdit
												value={ m.umysel || '' }
												onChange={ ( v ) => updateMass( dayIdx, ki, i, 'umysel', v ) }
												placeholder={ __( 'úmysel', 'farnost-plugin' ) }
												style={ { display: 'block', width: '100%', fontSize: 12, color: '#374151', marginTop: 2 } }
											/>
										</li>
									) ) }
								</ul>
							) }

							<Button
								variant="secondary"
								size="small"
								onClick={ () => addMass( dayIdx, ki ) }
								style={ { marginTop: 8 } }
							>
								{ __( '+ Pridať omšu', 'farnost-plugin' ) }
							</Button>
						</div>
					);
				} )
			) }
		</div>
	);
}

function formatSnapshotAt( iso ) {
	if ( ! iso ) {
		return '';
	}
	try {
		const d = new Date( iso );
		return d.toLocaleString( 'sk-SK', {
			day: 'numeric', month: 'long', year: 'numeric',
			hour: '2-digit', minute: '2-digit',
		} );
	} catch ( e ) {
		return iso;
	}
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'farnost-rozpis-snapshot-wrap' } );
	const rawDni = Array.isArray( attributes.dni ) ? attributes.dni : [];
	const dni = rawDni.map( normalizeDay );
	const [ refreshing, setRefreshing ] = useState( false );

	const persist = ( nextDni ) => setAttributes( { dni: nextDni } );

	const handleRefresh = async () => {
		const confirmed = window.confirm(
			__(
				'Tým prepíšete všetky úpravy v rozpise (časy, označenia, úmysly), ktoré ste tu spravili. Snapshot sa nahradí aktuálnymi dátami zo rozpisu omší, výnimiek a úmyslov.\n\nPokračovať?',
				'farnost-plugin'
			)
		);
		if ( ! confirmed ) {
			return;
		}
		if ( ! attributes.tyzdenOd || ! attributes.tyzdenDo ) {
			window.alert( __( 'Týždeň nie je nastavený — nemôžem obnoviť.', 'farnost-plugin' ) );
			return;
		}
		setRefreshing( true );
		try {
			const result = await apiFetch( {
				path: '/farnost/v1/snapshot/build',
				method: 'POST',
				data: {
					tyzdenOd: attributes.tyzdenOd,
					tyzdenDo: attributes.tyzdenDo,
				},
			} );
			setAttributes( {
				dni: Array.isArray( result.dni ) ? result.dni : [],
				snapshotAt: result.snapshotAt || '',
			} );
		} catch ( e ) {
			window.alert(
				sprintf(
					__( 'Obnovenie zlyhalo: %s', 'farnost-plugin' ),
					e?.message || String( e )
				)
			);
		} finally {
			setRefreshing( false );
		}
	};

	// `dni` (lokálny) je už normalizovaný. Pri persist-e dropneme legacy
	// `omse` pole z dňa (ak ešte v stave bolo), aby content v DB obsahoval
	// len nový shape — single source of truth.
	const persistNormalized = ( nextDni ) =>
		persist( nextDni.map( ( { omse: _legacy, ...rest } ) => rest ) );

	const updateMass = ( dayIdx, kostolIdx, massIdx, field, value ) => {
		const next = dni.map( ( d, i ) => {
			if ( i !== dayIdx ) return d;
			const kostoly = Array.isArray( d.kostoly ) ? d.kostoly : [];
			const nextKostoly = kostoly.map( ( k, ki ) => {
				if ( ki !== kostolIdx ) return k;
				const omse = Array.isArray( k.omse ) ? k.omse : [];
				const nextOmse = omse.map( ( m, j ) => ( j === massIdx ? { ...m, [ field ]: value } : m ) );
				return { ...k, omse: nextOmse };
			} );
			return { ...d, kostoly: nextKostoly };
		} );
		persistNormalized( next );
	};

	const addMass = ( dayIdx, kostolIdx ) => {
		const next = dni.map( ( d, i ) => {
			if ( i !== dayIdx ) return d;
			const kostoly = Array.isArray( d.kostoly ) ? d.kostoly : [];
			const nextKostoly = kostoly.map( ( k, ki ) => {
				if ( ki !== kostolIdx ) return k;
				const omse = Array.isArray( k.omse ) ? [ ...k.omse ] : [];
				omse.push( { cas: '', oznacenie: '', umysel: '', source: 'manual' } );
				return { ...k, omse };
			} );
			return { ...d, kostoly: nextKostoly };
		} );
		persistNormalized( next );
	};

	const removeMass = ( dayIdx, kostolIdx, massIdx ) => {
		const next = dni.map( ( d, i ) => {
			if ( i !== dayIdx ) return d;
			const kostoly = Array.isArray( d.kostoly ) ? d.kostoly : [];
			const nextKostoly = kostoly.map( ( k, ki ) => {
				if ( ki !== kostolIdx ) return k;
				const omse = Array.isArray( k.omse ) ? k.omse.filter( ( _, j ) => j !== massIdx ) : [];
				return { ...k, omse };
			} );
			return { ...d, kostoly: nextKostoly };
		} );
		persistNormalized( next );
	};

	return (
		<div { ...blockProps }>
			<style>{ `
				.farnost-rozpis-snapshot-wrap { margin: 16px 0; }
				.farnost-rozpis-snapshot-bar {
					display: flex; align-items: center; justify-content: space-between;
					padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb;
					border-radius: 6px; margin-bottom: 12px; font-size: 12px;
				}
				.farnost-rozpis-snapshot-bar__info { color: #6b7280; }
				.farnost-rozpis-snapshot {
					display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
					gap: 12px;
				}
				.farnost-rozpis-snapshot__card {
					border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; background: #fff;
				}
				.farnost-rozpis-snapshot__header {
					padding-bottom: 8px; border-bottom: 1px solid #f3f4f6; margin-bottom: 8px;
				}
				.farnost-rozpis-snapshot__kostol {
					border-left: 2px solid #e5e7eb; padding-left: 10px; margin: 8px 0 12px;
				}
				.farnost-rozpis-snapshot__kostol:last-child { margin-bottom: 0; }
				.farnost-rozpis-snapshot__kostol-title {
					font-size: 11px; font-weight: 600; letter-spacing: 0.04em;
					text-transform: uppercase; color: #6b7280; margin-bottom: 4px;
				}
				.farnost-rozpis-snapshot__date {
					margin-left: 8px; color: #6b7280; font-size: 12px;
				}
				.farnost-rozpis-snapshot__sviatok {
					margin-top: 2px; color: #6b7280; font-size: 12px; font-style: italic;
				}
				.farnost-rozpis-snapshot__list { margin: 0; padding: 0; list-style: none; }
				.farnost-rozpis-snapshot__row { padding: 6px 0; border-bottom: 1px dashed #f3f4f6; }
				.farnost-rozpis-snapshot__row:last-child { border-bottom: 0; }
				.farnost-rozpis-snapshot__row-line {
					display: flex; gap: 4px; align-items: center;
				}
				.farnost-rozpis-snapshot__empty {
					color: #9ca3af; font-style: italic; font-size: 12px; padding: 8px 0;
				}
				.farnost-rozpis-snapshot__inline {
					background: transparent; border: 1px solid transparent; padding: 2px 6px;
					border-radius: 3px; cursor: text; font-size: 13px; line-height: 20px;
					text-align: left;
				}
				.farnost-rozpis-snapshot__inline:hover {
					background: #f3f4f6; border-color: #e5e7eb;
				}
				.farnost-rozpis-snapshot__input {
					padding: 2px 6px; border-radius: 3px; border: 1px solid #1e40af;
					font-size: 13px; line-height: 20px; outline: none;
				}
			` }</style>

			<div className="farnost-rozpis-snapshot-bar">
				<div className="farnost-rozpis-snapshot-bar__info">
					{ attributes.snapshotAt
						? sprintf(
							__( 'Snapshot odobraný %s', 'farnost-plugin' ),
							formatSnapshotAt( attributes.snapshotAt )
						)
						: __( 'Snapshot ešte nebol odobraný.', 'farnost-plugin' ) }
				</div>
				<Button
					variant="secondary"
					size="small"
					onClick={ handleRefresh }
					disabled={ refreshing }
				>
					{ refreshing ? <Spinner /> : __( '↻ Obnoviť snapshot', 'farnost-plugin' ) }
				</Button>
			</div>

			{ dni.length === 0 ? (
				<div style={ { padding: 24, textAlign: 'center', color: '#6b7280', border: '1px dashed #d1d5db', borderRadius: 6 } }>
					{ __( 'Rozpis omší — prázdny snapshot. Pri vytvorení oznamu sa naplní automaticky.', 'farnost-plugin' ) }
				</div>
			) : (
				<div className="farnost-rozpis-snapshot">
					{ dni.map( ( day, idx ) => (
						<DayCardEdit
							key={ idx }
							day={ day }
							dayIdx={ idx }
							updateMass={ updateMass }
							addMass={ addMass }
							removeMass={ removeMass }
						/>
					) ) }
				</div>
			) }
		</div>
	);
}

// Dynamic block — frontend render robí PHP (src/Blocks/RozpisSnapshot.php).
function Save() {
	return null;
}

registerBlockType( 'farnost/rozpis-snapshot', {
	apiVersion: 3,
	title: __( 'Rozpis omší (snapshot)', 'farnost-plugin' ),
	description: __( 'Týždenný rozpis omší zamrznutý pri vytvorení oznamu. Edituje sa inline.', 'farnost-plugin' ),
	category: 'farnost',
	icon: 'calendar-alt',
	supports: {
		html: false,
		multiple: false,
	},
	attributes: {
		tyzdenOd:   { type: 'string', default: '' },
		tyzdenDo:   { type: 'string', default: '' },
		dni:        { type: 'array',  default: [] },
		snapshotAt: { type: 'string', default: '' },
	},
	edit: Edit,
	save: Save,
} );
