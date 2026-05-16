/**
 * Gutenberg sidebar panel pre natívny `post` CPT — udalosť farnosti.
 *
 * Polia: kedy (free-form datetime), kde (free-form miesto). Obe sú voliteľné —
 * post slúži aj ako bežný oznam bez detailov udalosti.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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

	return (
		<PluginDocumentSettingPanel
			name="farnost-udalost-detail"
			title={ __( 'Detail udalosti', 'farnost-plugin' ) }
			className="farnost-udalost-panel"
		>
			<TextControl
				label={ __( 'Kedy', 'farnost-plugin' ) }
				value={ eventWhen || '' }
				onChange={ ( v ) => setMeta( 'farnost_event_when', v ) }
				placeholder="YYYY-MM-DD HH:MM"
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
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
