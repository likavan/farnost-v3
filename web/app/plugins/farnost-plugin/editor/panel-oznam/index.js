/**
 * Gutenberg sidebar panel pre CPT `oznam` — týždeň, ku ktorému oznam patrí.
 *
 * Týždeň je **pevný** — určuje sa automaticky pri vytvorení oznamu (PHP:
 * `Oznam\AutoTemplate::computeNextWeek`). Tu sa zobrazuje len ako info, aby
 * farár nezmenil dátumy a tým nerozbil snapshot v rozpis-snapshot bloku.
 *
 * Životný cyklus oznamu viď doc/06-struktura-stranky.md.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

function formatLong( iso ) {
	if ( ! iso ) return '—';
	const d = new Date( iso + 'T12:00:00' );
	return d.toLocaleDateString( 'sk-SK', {
		weekday: 'long',
		day: 'numeric',
		month: 'long',
		year: 'numeric',
	} );
}

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

	if ( postType !== 'oznam' ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="farnost-oznam-tyzden"
			title={ __( 'Týždeň oznamu', 'farnost-plugin' ) }
			className="farnost-oznam-tyzden-panel"
		>
			<div style={ { fontSize: 13, lineHeight: 1.5 } }>
				<div style={ { display: 'flex', justifyContent: 'space-between', color: '#6b7280', fontSize: 12, marginBottom: 2 } }>
					<span>{ __( 'Od', 'farnost-plugin' ) }</span>
				</div>
				<div style={ { fontWeight: 600, marginBottom: 10 } }>
					{ formatLong( tyzdenOd ) }
				</div>
				<div style={ { display: 'flex', justifyContent: 'space-between', color: '#6b7280', fontSize: 12, marginBottom: 2 } }>
					<span>{ __( 'Do', 'farnost-plugin' ) }</span>
				</div>
				<div style={ { fontWeight: 600 } }>
					{ formatLong( tyzdenDo ) }
				</div>
			</div>
			<p style={ { color: '#6b7280', marginTop: 12, fontSize: 12 } }>
				{ __(
					'Týždeň oznamu je pevne určený pri vytvorení. Pre iný týždeň vytvorte nový oznam.',
					'farnost-plugin'
				) }
			</p>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'farnost-oznam-tyzden-panel', {
	render: OznamTyzdenPanel,
} );
