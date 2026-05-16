/**
 * Gutenberg sidebar panel pre CPT `oznam` — týždeň, ku ktorému oznam patrí.
 *
 * Oznam pokrýva jeden týždeň pondelok–nedeľa. Dve ISO dátumové polia
 * `farnost_tyzden_od` a `farnost_tyzden_do` definujú jeho rozsah.
 * Životný cyklus oznamu viď doc/06-struktura-stranky.md.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function OznamTyzdenPanel() {
	const { postType, tyzdenOd, tyzdenDo } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const meta = editor.getEditedPostAttribute( 'meta' ) || {};
		return {
			postType: editor.getCurrentPostType(),
			tyzdenOd: meta.farnost_tyzden_od ?? '',
			tyzdenDo: meta.farnost_tyzden_do ?? '',
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	if ( postType !== 'oznam' ) {
		return null;
	}

	const setMeta = ( field, value ) => {
		editPost( { meta: { [ field ]: value } } );
	};

	return (
		<PluginDocumentSettingPanel
			name="farnost-oznam-tyzden"
			title={ __( 'Týždeň oznamu', 'farnost-plugin' ) }
			className="farnost-oznam-tyzden-panel"
		>
			<div style={ { display: 'flex', gap: 8 } }>
				<div style={ { flex: 1 } }>
					<TextControl
						label={ __( 'Od pondelka', 'farnost-plugin' ) }
						type="date"
						value={ tyzdenOd || '' }
						onChange={ ( v ) => setMeta( 'farnost_tyzden_od', v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
				<div style={ { flex: 1 } }>
					<TextControl
						label={ __( 'Do nedele', 'farnost-plugin' ) }
						type="date"
						value={ tyzdenDo || '' }
						onChange={ ( v ) => setMeta( 'farnost_tyzden_do', v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			</div>
			<p style={ { color: '#6b7280', marginTop: 8, fontSize: 12 } }>
				{ __(
					'Oznam pokrýva jeden týždeň pondelok–nedeľa. Po publikovaní sa rozpis omší, úmysly a výnimky zamrazia ako snapshot.',
					'farnost-plugin'
				) }
			</p>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'farnost-oznam-tyzden-panel', {
	render: OznamTyzdenPanel,
} );
