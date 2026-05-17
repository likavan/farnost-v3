/**
 * Upratovacie skupiny — custom admin page (Farnosť → Upratovacie skupiny).
 *
 * Listing s drag-and-drop reorderom (`menu_order`), indikátorom „aktuálne na rade"
 * a tlačidlom pre manuálny posun pointra. Pridanie skupiny = inline (title only),
 * editácia detailov = klasický wp-admin post editor (post.php?action=edit).
 *
 * Drag-and-drop je natívne HTML5 (draggable + dragover/drop) — bez extra dep.
 */

import { createRoot, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Inline click-to-edit pole. Plain text v display móde, klik / focus → input
 * (alebo textarea pri multiline). Enter / blur commit, Escape revert.
 * Stop drag-and-drop kým je v edit móde, aby sa pri kliknutí nezačal drag riadku.
 */
function InlineEdit( { value, onCommit, placeholder, multiline = false, style } ) {
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
		const inputStyle = {
			width: '100%',
			padding: '4px 8px',
			border: '1px solid #1e40af',
			borderRadius: 3,
			background: '#fff',
			font: 'inherit',
			outline: 'none',
			boxSizing: 'border-box',
			...style,
		};
		if ( multiline ) {
			return (
				<textarea
					ref={ inputRef }
					value={ draft }
					rows={ 2 }
					onChange={ ( e ) => setDraft( e.target.value ) }
					onBlur={ commit }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Escape' ) {
							cancel();
						}
					} }
					onClick={ ( e ) => e.stopPropagation() }
					style={ inputStyle }
				/>
			);
		}
		return (
			<input
				ref={ inputRef }
				type="text"
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
				onClick={ ( e ) => e.stopPropagation() }
				style={ inputStyle }
			/>
		);
	}

	const isEmpty = value === '' || value == null;
	return (
		<button
			type="button"
			onClick={ ( e ) => {
				e.stopPropagation();
				setEditing( true );
			} }
			onFocus={ () => setEditing( true ) }
			onDragStart={ ( e ) => e.preventDefault() }
			className="farnost-uprat-inline"
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

// context=edit vráti aj title.raw (bez HTML entít), ktoré chceme na inline edit.
// Funguje, lebo admin obrazovka má capability edit_posts.
const REST_LIST = '/wp/v2/upratovacie-skupiny?context=edit&per_page=100&orderby=menu_order&order=asc&status=publish&_fields=id,title,menu_order,meta';
const REST_BASE = '/wp/v2/upratovacie-skupiny';
const REST_SETTINGS = '/farnost/v1/settings';
const REST_POINTER = '/farnost/v1/rotation-pointer';

function App() {
	const [ items, setItems ] = useState( [] );
	const [ pointer, setPointer ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ newTitle, setNewTitle ] = useState( '' );
	const [ dragIdx, setDragIdx ] = useState( null );
	const [ dragOverIdx, setDragOverIdx ] = useState( null );

	const fetchAll = () => {
		setLoading( true );
		return Promise.all( [
			apiFetch( { path: REST_LIST } ),
			apiFetch( { path: REST_SETTINGS } ),
		] )
			.then( ( [ list, settings ] ) => {
				setItems( Array.isArray( list ) ? list : [] );
				setPointer( Number( settings?.upratovanie?.dalsia_skupina || 0 ) );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || String( err ) );
				setLoading( false );
			} );
	};

	useEffect( () => {
		fetchAll();
	}, [] );

	const persistOrder = async ( reordered ) => {
		setSaving( true );
		try {
			// Posielame iba tie, ktorých menu_order sa zmenil.
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
		if ( t === '' ) {
			return;
		}
		setSaving( true );
		try {
			const maxOrder = items.reduce(
				( acc, it ) => Math.max( acc, Number( it.menu_order || 0 ) ),
				0
			);
			await apiFetch( {
				path: REST_BASE,
				method: 'POST',
				data: {
					title: t,
					status: 'publish',
					menu_order: maxOrder + 1,
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
		if ( ! window.confirm( __( 'Naozaj zmazať skupinu?', 'farnost-plugin' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			await apiFetch( {
				path: `${ REST_BASE }/${ id }?force=true`,
				method: 'DELETE',
			} );
			// Ak sa mazal aktuálny pointer, zhodíme ho — neresetujeme na ďalšiu,
			// nech farár vidí, že rotácia nemá hlavu a musí zvoliť.
			if ( id === pointer ) {
				try {
					await apiFetch( {
						path: REST_POINTER,
						method: 'POST',
						data: { id: 0 },
					} );
				} catch ( e ) {
					// best-effort, ignorujeme
				}
			}
			await fetchAll();
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	const handleSetPointer = async ( id ) => {
		setSaving( true );
		try {
			await apiFetch( {
				path: REST_POINTER,
				method: 'POST',
				data: { id },
			} );
			setPointer( id );
		} catch ( e ) {
			setError( e.message || String( e ) );
		} finally {
			setSaving( false );
		}
	};

	// Optimistický update + REST. Pri zlyhaní refetchneme, aby sa lokálny stav
	// zarovnal s realitou. Validácia (sanitize_text_field) ide cez WP REST sám.
	const handleUpdateTitle = async ( id, newTitle ) => {
		setItems( ( prev ) =>
			prev.map( ( it ) =>
				it.id === id ? { ...it, title: { ...it.title, raw: newTitle, rendered: newTitle } } : it
			)
		);
		try {
			await apiFetch( {
				path: `${ REST_BASE }/${ id }`,
				method: 'POST',
				data: { title: newTitle },
			} );
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

	const itemsRendered = useMemo( () => {
		return items.map( ( item, idx ) => {
			const isCurrent = Number( item.id ) === Number( pointer );
			const dragging = idx === dragIdx;
			const dragOver = idx === dragOverIdx && idx !== dragIdx;
			const title    = item.title?.raw ?? item.title?.rendered ?? '';
			const kontakt  = item.meta?.farnost_skupina_kontakt || '';
			const clenovia = item.meta?.farnost_skupina_clenovia || '';

			return (
				<div
					key={ item.id }
					className={ `farnost-uprat-row${ isCurrent ? ' is-current' : '' }${ dragging ? ' is-dragging' : '' }${ dragOver ? ' is-dragover' : '' }` }
					draggable
					onDragStart={ ( e ) => {
						setDragIdx( idx );
						// Required for Firefox to fire drop.
						e.dataTransfer.effectAllowed = 'move';
						e.dataTransfer.setData( 'text/plain', String( idx ) );
					} }
					onDragOver={ ( e ) => {
						e.preventDefault();
						e.dataTransfer.dropEffect = 'move';
						if ( idx !== dragOverIdx ) {
							setDragOverIdx( idx );
						}
					} }
					onDragLeave={ () => {
						if ( idx === dragOverIdx ) {
							setDragOverIdx( null );
						}
					} }
					onDrop={ ( e ) => {
						e.preventDefault();
						handleDrop( idx );
					} }
					onDragEnd={ () => {
						setDragIdx( null );
						setDragOverIdx( null );
					} }
				>
					<div className="farnost-uprat-handle" aria-hidden="true">☰</div>
					<div className="farnost-uprat-body">
						<div className="farnost-uprat-title-row">
							<InlineEdit
								value={ title }
								onCommit={ ( v ) => handleUpdateTitle( item.id, v.trim() === '' ? title : v ) }
								placeholder={ __( 'Názov skupiny', 'farnost-plugin' ) }
								style={ { fontWeight: 600, fontSize: 14, flex: 1 } }
							/>
							{ isCurrent && (
								<span className="farnost-uprat-badge">
									• { __( 'Aktuálne na rade', 'farnost-plugin' ) }
								</span>
							) }
						</div>
						<div className="farnost-uprat-meta-row">
							<span className="farnost-uprat-meta-label">{ __( 'Členovia:', 'farnost-plugin' ) }</span>
							<InlineEdit
								value={ clenovia }
								onCommit={ ( v ) => handleUpdateMeta( item.id, 'farnost_skupina_clenovia', v ) }
								placeholder={ __( 'napr. Mária N., Anna K., Helena P.', 'farnost-plugin' ) }
								multiline
								style={ { flex: 1, fontSize: 12 } }
							/>
						</div>
						<div className="farnost-uprat-meta-row">
							<span className="farnost-uprat-meta-label">{ __( 'Vedie:', 'farnost-plugin' ) }</span>
							<InlineEdit
								value={ kontakt }
								onCommit={ ( v ) => handleUpdateMeta( item.id, 'farnost_skupina_kontakt', v ) }
								placeholder={ __( 'napr. Anna K., 0905 123 456', 'farnost-plugin' ) }
								style={ { flex: 1, fontSize: 12 } }
							/>
						</div>
					</div>
					<div className="farnost-uprat-actions">
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => handleDelete( item.id ) }
							disabled={ saving }
						>
							{ __( 'Zmazať', 'farnost-plugin' ) }
						</Button>
						{ ! isCurrent && (
							<Button
								variant="link"
								onClick={ () => handleSetPointer( item.id ) }
								disabled={ saving }
							>
								{ __( 'Nastaviť ako ďalšiu na rade', 'farnost-plugin' ) }
							</Button>
						) }
					</div>
				</div>
			);
		} );
	}, [ items, pointer, dragIdx, dragOverIdx, saving ] );

	return (
		<div className="farnost-uprat">
			<style>{ `
				.farnost-uprat { max-width: 860px; }
				.farnost-uprat-add {
					display: flex; gap: 8px; align-items: flex-end;
					background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
					padding: 12px 16px; margin-bottom: 16px;
				}
				.farnost-uprat-add .components-base-control { flex: 1; margin-bottom: 0; }
				.farnost-uprat-list {
					background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
					overflow: hidden;
				}
				.farnost-uprat-row {
					display: flex; align-items: flex-start; gap: 12px;
					padding: 14px 16px; border-bottom: 1px solid #f3f4f6;
					background: #fff; cursor: grab;
				}
				.farnost-uprat-row:last-child { border-bottom: none; }
				.farnost-uprat-row:hover { background: #f9fafb; }
				.farnost-uprat-row.is-current { background: #eff6ff; }
				.farnost-uprat-row.is-current:hover { background: #dbeafe; }
				.farnost-uprat-row.is-dragging { opacity: 0.4; }
				.farnost-uprat-row.is-dragover {
					box-shadow: inset 0 3px 0 #1d4ed8;
				}
				.farnost-uprat-handle {
					font-size: 18px; color: #9ca3af; user-select: none; padding: 2px 4px;
					cursor: grab; line-height: 1;
				}
				.farnost-uprat-body { flex: 1; min-width: 0; }
				.farnost-uprat-title-row {
					display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
				}
				.farnost-uprat-title-row strong { font-size: 14px; }
				.farnost-uprat-badge {
					font-size: 12px; color: #1d4ed8; font-weight: 600;
				}
				.farnost-uprat-meta-row {
					display: flex; gap: 6px; align-items: flex-start; margin-top: 3px;
				}
				.farnost-uprat-meta-label {
					font-size: 12px; color: #6b7280; flex-shrink: 0;
					padding: 4px 0; min-width: 64px;
				}
				.farnost-uprat-inline {
					display: block; text-align: left; width: 100%;
					background: transparent; border: 1px solid transparent;
					padding: 4px 8px; border-radius: 3px; cursor: text;
					font: inherit; line-height: 1.4; min-height: 28px;
				}
				.farnost-uprat-inline:hover {
					background: #f3f4f6; border-color: #e5e7eb;
				}
				.farnost-uprat-actions {
					display: flex; flex-direction: column; gap: 4px; align-items: flex-end;
					flex-shrink: 0;
				}
				.farnost-uprat-empty {
					padding: 32px 16px; text-align: center; color: #6b7280;
					background: #fff; border: 1px dashed #d1d5db; border-radius: 6px;
				}
			` }</style>

			<div className="farnost-uprat-add">
				<TextControl
					label={ __( 'Pridať skupinu', 'farnost-plugin' ) }
					value={ newTitle }
					onChange={ setNewTitle }
					placeholder={ __( 'napr. Skupina sv. Jozefa', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							handleAdd();
						}
					} }
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
				<div className="farnost-uprat-empty">
					{ __( 'Zatiaľ žiadne skupiny. Pridajte prvú vyššie.', 'farnost-plugin' ) }
				</div>
			) }

			{ ! loading && ! error && items.length > 0 && (
				<div className="farnost-uprat-list">
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
	const mount = document.getElementById( 'farnost-upratovacie-root' );
	if ( mount ) {
		createRoot( mount ).render( <App /> );
	}
} );
