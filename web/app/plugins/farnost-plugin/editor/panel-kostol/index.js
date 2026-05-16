/**
 * Gutenberg sidebar panel pre CPT `kostol` — týždenný rozpis omší.
 *
 * Štruktúra: per deň zoznam slotov { day_of_week, time, oznacenie }.
 * Hodnota žije v post meta `farnost_rozpis` ako JSON string.
 *
 * UX: click-to-edit (Notion-style) — čas a označenie sa zobrazujú ako text,
 * klik prepne na input, blur / Enter uloží, Escape zruší.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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

/**
 * Inline click-to-edit field. Display mode = plain text; klik / focus
 * prepne na <input>. Blur alebo Enter commit-ne, Escape zruší.
 */
function InlineEdit( { value, onChange, placeholder, inputType = 'text', minWidth } ) {
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
				className="components-text-control__input"
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
				style={ { minWidth, padding: '4px 8px', height: 32 } }
			/>
		);
	}

	const isEmpty = value === '' || value == null;
	return (
		<button
			type="button"
			onClick={ () => setEditing( true ) }
			onFocus={ () => setEditing( true ) }
			className="farnost-inline-edit"
			style={ {
				display: 'inline-block',
				minWidth,
				padding: '4px 8px',
				background: 'transparent',
				border: '1px solid transparent',
				borderRadius: 3,
				textAlign: 'left',
				cursor: 'text',
				fontSize: 13,
				lineHeight: '24px',
				color: isEmpty ? '#9ca3af' : 'inherit',
				fontStyle: isEmpty ? 'italic' : 'normal',
			} }
		>
			{ isEmpty ? placeholder : value }
		</button>
	);
}

function RozpisOmsiPanel() {
	const { postType, rozpisRaw } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postType: editor.getCurrentPostType(),
			rozpisRaw: editor.getEditedPostAttribute( 'meta' )?.farnost_rozpis ?? '[]',
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	if ( postType !== 'kostol' ) {
		return null;
	}

	const rozpis = parseRozpis( rozpisRaw );

	const persist = ( next ) => {
		editPost( { meta: { farnost_rozpis: JSON.stringify( next ) } } );
	};

	const updateSlot = ( idx, field, value ) => {
		const next = rozpis.map( ( s, i ) => ( i === idx ? { ...s, [ field ]: value } : s ) );
		persist( next );
	};

	const addSlot = ( dayKey ) => {
		persist( [ ...rozpis, { day_of_week: dayKey, time: '', oznacenie: '' } ] );
	};

	const removeSlot = ( idx ) => {
		persist( rozpis.filter( ( _, i ) => i !== idx ) );
	};

	return (
		<PluginDocumentSettingPanel
			name="farnost-rozpis-omsi"
			title={ __( 'Rozpis omší', 'farnost-plugin' ) }
			className="farnost-rozpis-omsi-panel"
		>
			<style>
				{ `.farnost-inline-edit:hover { background: #f3f4f6; border-color: #e5e7eb !important; }` }
			</style>
			{ DAYS.map( ( day ) => {
				const slots = rozpis
					.map( ( s, idx ) => ( { ...s, idx } ) )
					.filter( ( s ) => s.day_of_week === day.key );

				return (
					<div key={ day.key } style={ { marginBottom: 16 } }>
						<strong style={ { display: 'block', marginBottom: 4 } }>{ day.label }</strong>
						{ slots.length === 0 && (
							<p style={ { color: '#6b7280', margin: '4px 0', fontSize: 12 } }>
								{ __( 'V tento deň nie je pravidelná omša.', 'farnost-plugin' ) }
							</p>
						) }
						{ slots.map( ( slot ) => (
							<div
								key={ slot.idx }
								style={ {
									display: 'flex',
									gap: 4,
									alignItems: 'center',
									marginBottom: 2,
								} }
							>
								<InlineEdit
									value={ slot.time || '' }
									onChange={ ( v ) => updateSlot( slot.idx, 'time', v ) }
									placeholder="HH:MM"
									inputType="text"
									minWidth={ 60 }
								/>
								<div style={ { flex: 1 } }>
									<InlineEdit
										value={ slot.oznacenie || '' }
										onChange={ ( v ) => updateSlot( slot.idx, 'oznacenie', v ) }
										placeholder={ __( 'bez označenia', 'farnost-plugin' ) }
										inputType="text"
									/>
								</div>
								<Button
									isDestructive
									variant="tertiary"
									onClick={ () => removeSlot( slot.idx ) }
									label={ __( 'Odstrániť omšu', 'farnost-plugin' ) }
									showTooltip
								>
									✕
								</Button>
							</div>
						) ) }
						<Button
							variant="secondary"
							size="small"
							onClick={ () => addSlot( day.key ) }
							style={ { marginTop: 4 } }
						>
							{ __( '+ Pridať omšu', 'farnost-plugin' ) }
						</Button>
					</div>
				);
			} ) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'farnost-rozpis-omsi-panel', {
	render: RozpisOmsiPanel,
} );
