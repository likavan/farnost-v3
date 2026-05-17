/**
 * Kostoly — custom admin page (Farnosť → Kostoly).
 *
 * Listing s inline edit pre názov, farbu (wp-color-picker / iris), „hlavný kostol"
 * toggle (exkluzívny — vždy je max. jeden hlavný), a expandable rozpisom omší per
 * riadok. Žiadny Gutenberg editor pre túto CPT — všetko sa deje tu.
 *
 * Drag-and-drop poradie (menu_order) cez native HTML5 dragstart/drop, rovnaký
 * pattern ako pri UpratovacieSkupinyPage.
 */

import { createRoot, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, ToggleControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const REST_LIST = '/wp/v2/kostoly?context=edit&per_page=100&orderby=menu_order&order=asc&status=publish&_fields=id,title,menu_order,meta';
const REST_BASE = '/wp/v2/kostoly';

// Fallback paleta pre kostoly bez explicitne nastavenej farby. Rovnaké hodnoty
// ako v calendar/index.js (pozičný fallback).
const FALLBACK_COLORS = [ '#1e40af', '#15803d', '#b45309', '#7c3aed', '#be185d', '#0e7490' ];

const DAYS = [
	{ key: 'mon', label: __( 'Pondelok', 'farnost-plugin' ) },
	{ key: 'tue', label: __( 'Utorok', 'farnost-plugin' ) },
	{ key: 'wed', label: __( 'Streda', 'farnost-plugin' ) },
	{ key: 'thu', label: __( 'Štvrtok', 'farnost-plugin' ) },
	{ key: 'fri', label: __( 'Piatok', 'farnost-plugin' ) },
	{ key: 'sat', label: __( 'Sobota', 'farnost-plugin' ) },
	{ key: 'sun', label: __( 'Nedeľa', 'farnost-plugin' ) },
];

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

function effectiveColor( meta, idx ) {
	const c = meta?.farnost_color;
	if ( typeof c === 'string' && /^#[A-Fa-f0-9]{3,6}$/.test( c ) ) {
		return c;
	}
	return FALLBACK_COLORS[ idx % FALLBACK_COLORS.length ];
}

/**
 * Inline click-to-edit pole — analogické s UpratovacieSkupinyPage InlineEdit.
 * Stop drag-and-drop kým je v edit móde, aby sa pri kliknutí nezačal drag riadku.
 */
function InlineEdit( { value, onCommit, placeholder, style } ) {
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
			onCommit( draft );
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
				type="text"
				value={ draft }
				onChange={ ( e ) => setDraft( e.target.value ) }
				onBlur={ commit }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) commit();
					else if ( e.key === 'Escape' ) cancel();
				} }
				onClick={ ( e ) => e.stopPropagation() }
				className="farnost-kostoly-input"
				style={ style }
			/>
		);
	}

	const isEmpty = value === '' || value == null;
	return (
		<button
			type="button"
			onClick={ ( e ) => { e.stopPropagation(); setEditing( true ); } }
			onFocus={ () => setEditing( true ) }
			onDragStart={ ( e ) => e.preventDefault() }
			className="farnost-kostoly-inline"
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

/**
 * Color swatch — kliknutie otvorí wp-color-picker popover. Implementácia cez iris
 * (jQuery), ktorá je v admine k dispozícii.
 */
function ColorSwatch( { value, onCommit } ) {
	const [ open, setOpen ] = useState( false );
	const wrapRef = useRef( null );
	const inputRef = useRef( null );

	useEffect( () => {
		if ( ! open || ! inputRef.current || ! window.jQuery ) {
			return;
		}
		const $input = window.jQuery( inputRef.current );
		$input.wpColorPicker( {
			defaultColor: value || '',
			change: ( _event, ui ) => {
				const next = ui.color.toString();
				if ( next !== value ) {
					onCommit( next );
				}
			},
			clear: () => {
				onCommit( '' );
			},
		} );
		return () => {
			try { $input.wpColorPicker( 'close' ); } catch ( e ) {}
		};
	}, [ open ] );

	// Zatvor pri kliku mimo
	useEffect( () => {
		if ( ! open ) return;
		const handler = ( e ) => {
			if ( wrapRef.current && ! wrapRef.current.contains( e.target ) ) {
				setOpen( false );
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ open ] );

	const displayColor = value || '#9ca3af';
	const isExplicit = !! value;

	return (
		<div ref={ wrapRef } style={ { position: 'relative', flexShrink: 0 } }>
			<button
				type="button"
				onClick={ ( e ) => { e.stopPropagation(); setOpen( ! open ); } }
				onDragStart={ ( e ) => e.preventDefault() }
				title={ isExplicit ? value : __( 'Farba nie je nastavená (pozičný fallback)', 'farnost-plugin' ) }
				style={ {
					width: 24, height: 24, borderRadius: 4,
					background: displayColor,
					border: isExplicit ? '1px solid rgba(0,0,0,0.2)' : '1px dashed #9ca3af',
					cursor: 'pointer', padding: 0,
				} }
				aria-label={ __( 'Zmeniť farbu kostola', 'farnost-plugin' ) }
			/>
			{ open && (
				<div style={ {
					position: 'absolute', top: '110%', left: 0, zIndex: 100,
					background: '#fff', border: '1px solid #e5e7eb', borderRadius: 4,
					padding: 8, boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
				} }>
					<input ref={ inputRef } type="text" defaultValue={ value || '' } />
				</div>
			) }
		</div>
	);
}

function RozpisGrid( { rozpis, onChange } ) {
	const updateSlot = ( idx, field, value ) => {
		onChange( rozpis.map( ( s, i ) => ( i === idx ? { ...s, [ field ]: value } : s ) ) );
	};
	const addSlot = ( dayKey ) => {
		onChange( [ ...rozpis, { day_of_week: dayKey, time: '', oznacenie: '' } ] );
	};
	const removeSlot = ( idx ) => {
		onChange( rozpis.filter( ( _, i ) => i !== idx ) );
	};

	return (
		<div className="farnost-kostoly-rozpis">
			{ DAYS.map( ( day ) => {
				const slots = rozpis
					.map( ( s, idx ) => ( { ...s, idx } ) )
					.filter( ( s ) => s.day_of_week === day.key );
				return (
					<div key={ day.key } className="farnost-kostoly-rozpis-day">
						<div className="farnost-kostoly-rozpis-day-label">{ day.label }</div>
						<div className="farnost-kostoly-rozpis-day-slots">
							{ slots.length === 0 && (
								<span className="farnost-kostoly-rozpis-empty">
									{ __( 'nič', 'farnost-plugin' ) }
								</span>
							) }
							{ slots.map( ( slot ) => (
								<div key={ slot.idx } className="farnost-kostoly-rozpis-slot">
									<InlineEdit
										value={ slot.time || '' }
										onCommit={ ( v ) => updateSlot( slot.idx, 'time', v ) }
										placeholder="HH:MM"
										style={ { width: 56, fontWeight: 600 } }
									/>
									<InlineEdit
										value={ slot.oznacenie || '' }
										onCommit={ ( v ) => updateSlot( slot.idx, 'oznacenie', v ) }
										placeholder={ __( 'označenie', 'farnost-plugin' ) }
										style={ { flex: 1, fontSize: 12, color: '#6b7280' } }
									/>
									<Button
										isDestructive
										variant="tertiary"
										size="small"
										onClick={ () => removeSlot( slot.idx ) }
										label={ __( 'Odstrániť omšu', 'farnost-plugin' ) }
										showTooltip
									>✕</Button>
								</div>
							) ) }
							<Button
								variant="link"
								size="small"
								onClick={ () => addSlot( day.key ) }
								style={ { fontSize: 11 } }
							>
								{ __( '+ Pridať omšu', 'farnost-plugin' ) }
							</Button>
						</div>
					</div>
				);
			} ) }
		</div>
	);
}

function App() {
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ newTitle, setNewTitle ] = useState( '' );
	const [ expandedId, setExpandedId ] = useState( null );
	const [ dragIdx, setDragIdx ] = useState( null );
	const [ dragOverIdx, setDragOverIdx ] = useState( null );

	const fetchAll = () => {
		setLoading( true );
		return apiFetch( { path: REST_LIST } )
			.then( ( list ) => {
				setItems( Array.isArray( list ) ? list : [] );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || String( err ) );
				setLoading( false );
			} );
	};

	useEffect( () => { fetchAll(); }, [] );

	const persistOrder = async ( reordered ) => {
		setSaving( true );
		try {
			const updates = [];
			reordered.forEach( ( item, idx ) => {
				const target = idx + 1;
				if ( Number( item.menu_order ) !== target ) {
					updates.push(
						apiFetch( {
							path: `${ REST_BASE }/${ item.id }`,
							method: 'POST',
							data: { menu_order: target },
						} )
					);
				}
			} );
			await Promise.all( updates );
			await fetchAll();
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	const handleDrop = ( targetIdx ) => {
		if ( dragIdx === null || dragIdx === targetIdx ) {
			setDragIdx( null );
			setDragOverIdx( null );
			return;
		}
		const next = items.slice();
		const [ moved ] = next.splice( dragIdx, 1 );
		next.splice( targetIdx, 0, moved );
		setItems( next );
		setDragIdx( null );
		setDragOverIdx( null );
		void persistOrder( next );
	};

	const handleAdd = async () => {
		const t = newTitle.trim();
		if ( t === '' ) return;
		setSaving( true );
		try {
			const maxOrder = items.reduce(
				( acc, it ) => Math.max( acc, Number( it.menu_order || 0 ) ),
				0
			);
			// Auto-pridelíme nasledujúcu farbu z palety podľa počtu existujúcich kostolov.
			const nextColor = FALLBACK_COLORS[ items.length % FALLBACK_COLORS.length ];
			await apiFetch( {
				path: REST_BASE,
				method: 'POST',
				data: {
					title: t,
					status: 'publish',
					menu_order: maxOrder + 1,
					meta: { farnost_color: nextColor },
				},
			} );
			setNewTitle( '' );
			await fetchAll();
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	const handleDelete = async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Naozaj zmazať kostol? Rozpis omší aj všetky výnimky / úmysly pre tento kostol stratíte.', 'farnost-plugin' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			await apiFetch( {
				path: `${ REST_BASE }/${ id }?force=true`,
				method: 'DELETE',
			} );
			await fetchAll();
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	// Optimistický update s REST. Pri zlyhaní refetch.
	const handleUpdateTitle = async ( id, newT ) => {
		setItems( ( prev ) =>
			prev.map( ( it ) =>
				it.id === id ? { ...it, title: { ...it.title, raw: newT, rendered: newT } } : it
			)
		);
		try {
			await apiFetch( { path: `${ REST_BASE }/${ id }`, method: 'POST', data: { title: newT } } );
		} catch ( e ) {
			setError( e.message || String( e ) );
			void fetchAll();
		}
	};

	const handleUpdateMeta = async ( id, key, value ) => {
		setItems( ( prev ) =>
			prev.map( ( it ) =>
				it.id === id ? { ...it, meta: { ...( it.meta || {} ), [ key ]: value } } : it
			)
		);
		try {
			await apiFetch( {
				path: `${ REST_BASE }/${ id }`,
				method: 'POST',
				data: { meta: { [ key ]: value } },
			} );
		} catch ( e ) {
			setError( e.message || String( e ) );
			void fetchAll();
		}
	};

	// „Hlavný" je exkluzívny — zapnutie jedného vypne všetky ostatné. Robíme to
	// optimisticky lokálne, potom paralelne REST POST na zmenené posty. Pri zlyhaní
	// refetchneme.
	const handleToggleHlavny = async ( id, newValue ) => {
		setItems( ( prev ) =>
			prev.map( ( it ) => {
				if ( it.id === id ) {
					return { ...it, meta: { ...( it.meta || {} ), farnost_je_hlavny: newValue } };
				}
				if ( newValue && it.meta?.farnost_je_hlavny ) {
					return { ...it, meta: { ...( it.meta || {} ), farnost_je_hlavny: false } };
				}
				return it;
			} )
		);
		setSaving( true );
		try {
			const updates = [
				apiFetch( {
					path: `${ REST_BASE }/${ id }`,
					method: 'POST',
					data: { meta: { farnost_je_hlavny: newValue } },
				} ),
			];
			if ( newValue ) {
				items.forEach( ( it ) => {
					if ( it.id !== id && it.meta?.farnost_je_hlavny ) {
						updates.push(
							apiFetch( {
								path: `${ REST_BASE }/${ it.id }`,
								method: 'POST',
								data: { meta: { farnost_je_hlavny: false } },
							} )
						);
					}
				} );
			}
			await Promise.all( updates );
		} catch ( e ) {
			setError( e.message || String( e ) );
			void fetchAll();
		} finally {
			setSaving( false );
		}
	};

	const handleUpdateRozpis = ( id, rozpisArray ) => {
		const raw = JSON.stringify( rozpisArray );
		handleUpdateMeta( id, 'farnost_rozpis', raw );
	};

	const itemsRendered = useMemo( () => {
		return items.map( ( item, idx ) => {
			const dragging = idx === dragIdx;
			const dragOver = idx === dragOverIdx && idx !== dragIdx;
			const title = item.title?.raw ?? item.title?.rendered ?? '';
			const color = effectiveColor( item.meta, idx );
			const isHlavny = !! item.meta?.farnost_je_hlavny;
			const rozpis = parseRozpis( item.meta?.farnost_rozpis ?? '[]' );
			const isExpanded = expandedId === item.id;
			const slotCount = rozpis.filter( ( s ) => !! s.time ).length;

			return (
				<div key={ item.id } className="farnost-kostoly-item-wrap">
					<div
						className={ `farnost-kostoly-row${ dragging ? ' is-dragging' : '' }${ dragOver ? ' is-dragover' : '' }${ isExpanded ? ' is-expanded' : '' }` }
						draggable
						onDragStart={ ( e ) => {
							setDragIdx( idx );
							e.dataTransfer.effectAllowed = 'move';
							e.dataTransfer.setData( 'text/plain', String( idx ) );
						} }
						onDragOver={ ( e ) => {
							e.preventDefault();
							e.dataTransfer.dropEffect = 'move';
							if ( idx !== dragOverIdx ) setDragOverIdx( idx );
						} }
						onDragLeave={ () => {
							if ( idx === dragOverIdx ) setDragOverIdx( null );
						} }
						onDrop={ ( e ) => { e.preventDefault(); handleDrop( idx ); } }
						onDragEnd={ () => { setDragIdx( null ); setDragOverIdx( null ); } }
					>
						<div className="farnost-kostoly-handle" aria-hidden="true">☰</div>
						<ColorSwatch
							value={ item.meta?.farnost_color || '' }
							onCommit={ ( v ) => handleUpdateMeta( item.id, 'farnost_color', v ) }
						/>
						<div className="farnost-kostoly-title">
							<InlineEdit
								value={ title }
								onCommit={ ( v ) => handleUpdateTitle( item.id, v.trim() === '' ? title : v ) }
								placeholder={ __( 'Názov kostola', 'farnost-plugin' ) }
								style={ { fontWeight: 600, fontSize: 14, width: '100%' } }
							/>
						</div>
						<div className="farnost-kostoly-hlavny" onClick={ ( e ) => e.stopPropagation() }>
							<ToggleControl
								label={ __( 'Hlavný', 'farnost-plugin' ) }
								checked={ isHlavny }
								onChange={ ( v ) => handleToggleHlavny( item.id, v ) }
								disabled={ saving }
								__nextHasNoMarginBottom
							/>
						</div>
						<Button
							variant={ isExpanded ? 'primary' : 'secondary' }
							size="small"
							onClick={ ( e ) => {
								e.stopPropagation();
								setExpandedId( isExpanded ? null : item.id );
							} }
							onDragStart={ ( e ) => e.preventDefault() }
						>
							{ isExpanded
								? __( 'Skryť rozpis', 'farnost-plugin' )
								: sprintf( __( 'Rozpis (%d)', 'farnost-plugin' ), slotCount )
							}
						</Button>
						<Button
							variant="tertiary"
							isDestructive
							size="small"
							onClick={ ( e ) => { e.stopPropagation(); handleDelete( item.id ); } }
							onDragStart={ ( e ) => e.preventDefault() }
							disabled={ saving }
						>
							{ __( 'Zmazať', 'farnost-plugin' ) }
						</Button>
					</div>
					{ isExpanded && (
						<div className="farnost-kostoly-expanded">
							<RozpisGrid
								rozpis={ rozpis }
								onChange={ ( next ) => handleUpdateRozpis( item.id, next ) }
							/>
						</div>
					) }
					{ /* indikátor farby pre expand panel — pomáha vizuálne spojiť detail s rowom */ }
				</div>
			);
		} );
	}, [ items, expandedId, dragIdx, dragOverIdx, saving ] );

	return (
		<div className="farnost-kostoly">
			<style>{ `
				.farnost-kostoly { max-width: 1000px; }
				.farnost-kostoly-add {
					display: flex; gap: 8px; align-items: center;
					background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
					padding: 12px 16px; margin-bottom: 16px;
				}
				.farnost-kostoly-add input {
					flex: 1; padding: 6px 10px; border: 1px solid #d1d5db;
					border-radius: 3px; font-size: 14px;
				}
				.farnost-kostoly-list {
					background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
					overflow: hidden;
				}
				.farnost-kostoly-item-wrap { border-bottom: 1px solid #f3f4f6; }
				.farnost-kostoly-item-wrap:last-child { border-bottom: none; }
				.farnost-kostoly-row {
					display: flex; align-items: center; gap: 12px;
					padding: 12px 16px; cursor: grab; background: #fff;
				}
				.farnost-kostoly-row:hover { background: #f9fafb; }
				.farnost-kostoly-row.is-expanded { background: #f9fafb; }
				.farnost-kostoly-row.is-dragging { opacity: 0.4; }
				.farnost-kostoly-row.is-dragover { box-shadow: inset 0 3px 0 #1d4ed8; }
				.farnost-kostoly-handle {
					font-size: 18px; color: #9ca3af; user-select: none;
					padding: 2px 4px; line-height: 1;
				}
				.farnost-kostoly-title { flex: 1; min-width: 0; }
				.farnost-kostoly-hlavny {
					min-width: 88px;
				}
				.farnost-kostoly-hlavny .components-toggle-control__label {
					font-size: 12px; color: #374151;
				}
				.farnost-kostoly-inline {
					display: block; text-align: left; width: 100%;
					background: transparent; border: 1px solid transparent;
					padding: 4px 8px; border-radius: 3px; cursor: text;
					font: inherit; line-height: 1.4; min-height: 28px;
				}
				.farnost-kostoly-inline:hover {
					background: #f3f4f6; border-color: #e5e7eb;
				}
				.farnost-kostoly-input {
					width: 100%; padding: 4px 8px; border: 1px solid #1e40af;
					border-radius: 3px; background: #fff; font: inherit;
					line-height: 1.4; outline: none; box-sizing: border-box;
				}
				.farnost-kostoly-expanded {
					padding: 16px 24px 20px; background: #f9fafb;
					border-top: 1px solid #f3f4f6;
				}
				.farnost-kostoly-rozpis {
					display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
					gap: 12px;
				}
				.farnost-kostoly-rozpis-day {
					background: #fff; border: 1px solid #e5e7eb; border-radius: 4px;
					padding: 8px 10px;
				}
				.farnost-kostoly-rozpis-day-label {
					font-weight: 600; font-size: 12px; margin-bottom: 4px;
					color: #374151;
				}
				.farnost-kostoly-rozpis-day-slots { display: flex; flex-direction: column; gap: 2px; }
				.farnost-kostoly-rozpis-slot {
					display: flex; gap: 4px; align-items: center;
				}
				.farnost-kostoly-rozpis-empty {
					font-size: 11px; color: #9ca3af; font-style: italic;
					padding: 2px 4px;
				}
				.farnost-kostoly-empty {
					padding: 32px 16px; text-align: center; color: #6b7280;
					background: #fff; border: 1px dashed #d1d5db; border-radius: 6px;
				}
			` }</style>

			<div className="farnost-kostoly-add">
				<input
					type="text"
					value={ newTitle }
					onChange={ ( e ) => setNewTitle( e.target.value ) }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							handleAdd();
						}
					} }
					placeholder={ __( 'Pridať kostol (napr. Farský kostol sv. Martina)', 'farnost-plugin' ) }
				/>
				<Button
					variant="primary"
					onClick={ handleAdd }
					disabled={ saving || newTitle.trim() === '' }
				>
					{ __( 'Pridať', 'farnost-plugin' ) }
				</Button>
			</div>

			{ loading && <p>{ __( 'Načítavam…', 'farnost-plugin' ) }</p> }
			{ error && (
				<p style={ { color: '#b32d2e' } }>
					{ sprintf( __( 'Chyba: %s', 'farnost-plugin' ), error ) }
				</p>
			) }

			{ ! loading && ! error && items.length === 0 && (
				<div className="farnost-kostoly-empty">
					{ __( 'Zatiaľ žiadne kostoly. Pridajte prvý vyššie.', 'farnost-plugin' ) }
				</div>
			) }

			{ ! loading && ! error && items.length > 0 && (
				<div className="farnost-kostoly-list">
					{ itemsRendered }
				</div>
			) }

			{ saving && (
				<p style={ { marginTop: 12, color: '#6b7280', fontSize: 12 } }>
					<Spinner /> { __( 'Ukladám…', 'farnost-plugin' ) }
				</p>
			) }
		</div>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	const mount = document.getElementById( 'farnost-kostoly-root' );
	if ( mount ) {
		createRoot( mount ).render( <App /> );
	}
} );
