/**
 * Gutenberg sidebar panel pre natívny `post` CPT — udalosť farnosti.
 *
 * Polia: kedy (date + čas cez DateTimePicker v dropdowne), kde (free-form
 * miesto). Obe sú voliteľné — post slúži aj ako bežný oznam bez detailov.
 *
 * Meta `farnost_event_when` sa ukladá ako "YYYY-MM-DD HH:MM" — Feed.php to
 * parsuje a lokalizuje cez wp_date(). Spätná kompatibilita: legacy free-form
 * text (napr. „Sobota 18:30") ostane v meta a Feed ho vypíše ako-is.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { TextControl, Button, DateTimePicker, Dropdown, BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STORED_FORMAT_RE = /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/;

/**
 * "YYYY-MM-DD HH:MM" → "YYYY-MM-DDTHH:MM:00" pre DateTimePicker.
 * Vráti null ak hodnota nie je vo formáte (legacy free-form text).
 */
function toIso( stored ) {
	if ( ! stored ) return null;
	const m = STORED_FORMAT_RE.exec( stored );
	if ( ! m ) return null;
	return `${ m[ 1 ] }T${ m[ 2 ] }:00`;
}

/** "YYYY-MM-DDTHH:MM:SS" → "YYYY-MM-DD HH:MM". */
function fromIso( iso ) {
	if ( ! iso ) return '';
	return `${ iso.slice( 0, 10 ) } ${ iso.slice( 11, 16 ) }`;
}

/**
 * Slovenský display formát pre toggle button: "20. 5. 2026, 18:00".
 * Pre legacy free-form text vraciame ho ako-is.
 */
function formatDisplay( stored ) {
	const m = STORED_FORMAT_RE.exec( stored || '' );
	if ( ! m ) {
		return stored || '';
	}
	const [ y, mo, d ] = m[ 1 ].split( '-' );
	return `${ parseInt( d, 10 ) }. ${ parseInt( mo, 10 ) }. ${ y }, ${ m[ 2 ] }`;
}

function UdalostPanel() {
	const { postType, eventWhen, eventWhere } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const meta = editor.getEditedPostAttribute( 'meta' ) || {};
		return {
			postType: editor.getCurrentPostType(),
			eventWhen: meta.farnost_event_when ?? '',
			eventWhere: meta.farnost_event_where ?? '',
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	if ( postType !== 'post' ) {
		return null;
	}

	const setMeta = ( field, value ) => {
		editPost( { meta: { [ field ]: value } } );
	};

	const isoCurrent = toIso( eventWhen );

	return (
		<PluginDocumentSettingPanel
			name="farnost-udalost-detail"
			title={ __( 'Detail udalosti', 'farnost-plugin' ) }
			className="farnost-udalost-panel"
		>
			<BaseControl
				label={ __( 'Kedy', 'farnost-plugin' ) }
				id="farnost-event-when"
				__nextHasNoMarginBottom
			>
				<Dropdown
					popoverProps={ { placement: 'bottom-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="secondary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							id="farnost-event-when"
							style={ { width: '100%', justifyContent: 'flex-start' } }
						>
							{ eventWhen
								? formatDisplay( eventWhen )
								: __( 'Vybrať dátum a čas…', 'farnost-plugin' ) }
						</Button>
					) }
					renderContent={ () => (
						<div style={ { padding: 12, minWidth: 280 } }>
							<DateTimePicker
								currentDate={ isoCurrent }
								onChange={ ( iso ) => setMeta( 'farnost_event_when', fromIso( iso ) ) }
								is12Hour={ false }
								__nextRemoveHelpButton
								__nextRemoveResetButton
							/>
							{ eventWhen && (
								<div style={ { marginTop: 8, textAlign: 'right' } }>
									<Button
										variant="link"
										isDestructive
										onClick={ () => setMeta( 'farnost_event_when', '' ) }
									>
										{ __( 'Vyčistiť', 'farnost-plugin' ) }
									</Button>
								</div>
							) }
						</div>
					) }
				/>
			</BaseControl>
			<div style={ { marginTop: 12 } }>
				<TextControl
					label={ __( 'Kde', 'farnost-plugin' ) }
					value={ eventWhere || '' }
					onChange={ ( v ) => setMeta( 'farnost_event_where', v ) }
					placeholder={ __( 'voliteľný popis miesta', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<p style={ { color: '#6b7280', marginTop: 8, fontSize: 12 } }>
				{ __(
					'Udalosť sa zobrazí vo feed-e farnosti. Polia Kedy/Kde sú voliteľné.',
					'farnost-plugin'
				) }
			</p>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'farnost-udalost-panel', {
	render: UdalostPanel,
} );
