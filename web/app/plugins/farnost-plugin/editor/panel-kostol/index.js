/**
 * Gutenberg sidebar panel pre CPT `kostol` — týždenný rozpis omší.
 *
 * Štruktúra: per deň zoznam slotov { day_of_week, time, oznacenie }.
 * Hodnota žije v post meta `farnost_rozpis` ako JSON string.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { TextControl, Button } from '@wordpress/components';
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
									gap: 6,
									alignItems: 'flex-end',
									marginBottom: 4,
								} }
							>
								<div style={ { width: 70 } }>
									<TextControl
										label={ __( 'Čas', 'farnost-plugin' ) }
										value={ slot.time || '' }
										onChange={ ( v ) => updateSlot( slot.idx, 'time', v ) }
										placeholder="HH:MM"
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								</div>
								<div style={ { flex: 1 } }>
									<TextControl
										label={ __( 'Označenie', 'farnost-plugin' ) }
										value={ slot.oznacenie || '' }
										onChange={ ( v ) => updateSlot( slot.idx, 'oznacenie', v ) }
										placeholder={ __( 'voliteľné', 'farnost-plugin' ) }
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								</div>
								<Button
									isDestructive
									variant="tertiary"
									onClick={ () => removeSlot( slot.idx ) }
									label={ __( 'Odstrániť omšu', 'farnost-plugin' ) }
									showTooltip
									__next40pxDefaultSize
								>
									✕
								</Button>
							</div>
						) ) }
						<Button
							variant="secondary"
							size="small"
							onClick={ () => addSlot( day.key ) }
							__next40pxDefaultSize
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
