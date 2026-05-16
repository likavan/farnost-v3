/**
 * Block `farnost/rozpis-snapshot` — snapshot rozpisu omší v ozname.
 *
 * Atribúty:
 *   - tyzdenOd, tyzdenDo: 'YYYY-MM-DD'
 *   - dni: pole 7 položiek
 *       { date, dayKey, sviatok, omse: [{ kostol_title, time, oznacenie, umysel, source }] }
 *
 * Snapshot model: dáta sa kopírujú do post_content pri vytvorení oznamu (server-side
 * v ďalšom commit-e). Po publikovaní oznamu sa zmena v podkladových dátach
 * neprejaví — to je presne ten zmysel: oznam je „zamrznutý" záznam.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const DAY_LABELS = {
	mon: __( 'Pondelok', 'farnost-plugin' ),
	tue: __( 'Utorok', 'farnost-plugin' ),
	wed: __( 'Streda', 'farnost-plugin' ),
	thu: __( 'Štvrtok', 'farnost-plugin' ),
	fri: __( 'Piatok', 'farnost-plugin' ),
	sat: __( 'Sobota', 'farnost-plugin' ),
	sun: __( 'Nedeľa', 'farnost-plugin' ),
};

function DayCard( { day } ) {
	const label = DAY_LABELS[ day.dayKey ] || day.dayKey;
	const omse = Array.isArray( day.omse ) ? day.omse : [];
	return (
		<div className="farnost-rozpis-snapshot__card">
			<div className="farnost-rozpis-snapshot__header">
				<strong>{ label }</strong>
				{ day.date && (
					<span className="farnost-rozpis-snapshot__date">
						{ formatShortDate( day.date ) }
					</span>
				) }
				{ day.sviatok && (
					<div className="farnost-rozpis-snapshot__sviatok">{ day.sviatok }</div>
				) }
			</div>
			{ omse.length === 0 ? (
				<div className="farnost-rozpis-snapshot__empty">
					{ __( 'Sv. omša nie je', 'farnost-plugin' ) }
				</div>
			) : (
				<ul className="farnost-rozpis-snapshot__list">
					{ omse.map( ( m, i ) => (
						<li key={ i }>
							<span className="farnost-rozpis-snapshot__time">{ m.time }</span>
							{ m.oznacenie && (
								<span className="farnost-rozpis-snapshot__oznacenie"> · { m.oznacenie }</span>
							) }
							{ m.umysel && (
								<div className="farnost-rozpis-snapshot__umysel">{ m.umysel }</div>
							) }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

function formatShortDate( iso ) {
	const d = new Date( iso + 'T12:00:00' );
	return d.toLocaleDateString( 'sk-SK', { day: 'numeric', month: 'numeric' } );
}

function Edit( { attributes } ) {
	const blockProps = useBlockProps( { className: 'farnost-rozpis-snapshot' } );
	const dni = Array.isArray( attributes.dni ) ? attributes.dni : [];

	return (
		<div { ...blockProps }>
			<style>{ `
				.farnost-rozpis-snapshot {
					display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 12px; margin: 16px 0;
				}
				.farnost-rozpis-snapshot__card {
					border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; background: #fff;
				}
				.farnost-rozpis-snapshot__header {
					padding-bottom: 8px; border-bottom: 1px solid #f3f4f6; margin-bottom: 8px;
				}
				.farnost-rozpis-snapshot__date {
					margin-left: 8px; color: #6b7280; font-size: 12px;
				}
				.farnost-rozpis-snapshot__sviatok {
					margin-top: 2px; color: #6b7280; font-size: 12px; font-style: italic;
				}
				.farnost-rozpis-snapshot__list { margin: 0; padding: 0; list-style: none; }
				.farnost-rozpis-snapshot__list li { padding: 4px 0; font-size: 13px; }
				.farnost-rozpis-snapshot__time { font-weight: 600; }
				.farnost-rozpis-snapshot__oznacenie { color: #6b7280; }
				.farnost-rozpis-snapshot__umysel { color: #374151; font-size: 12px; margin-top: 2px; }
				.farnost-rozpis-snapshot__empty {
					color: #9ca3af; font-style: italic; font-size: 12px;
				}
			` }</style>

			{ dni.length === 0 ? (
				<div style={ { padding: 24, textAlign: 'center', color: '#6b7280', border: '1px dashed #d1d5db', borderRadius: 6 } }>
					{ __( 'Rozpis omší — prázdny snapshot. Pri vytvorení oznamu sa naplní automaticky.', 'farnost-plugin' ) }
				</div>
			) : (
				dni.map( ( day, idx ) => <DayCard key={ idx } day={ day } /> )
			) }
		</div>
	);
}

function Save( { attributes } ) {
	const blockProps = useBlockProps.save( { className: 'farnost-rozpis-snapshot' } );
	const dni = Array.isArray( attributes.dni ) ? attributes.dni : [];

	return (
		<div { ...blockProps }>
			{ dni.map( ( day, idx ) => {
				const label = DAY_LABELS[ day.dayKey ] || day.dayKey;
				const omse = Array.isArray( day.omse ) ? day.omse : [];
				return (
					<div key={ idx } className="farnost-rozpis-snapshot__card">
						<div className="farnost-rozpis-snapshot__header">
							<strong>{ label }</strong>
							{ day.date && (
								<span className="farnost-rozpis-snapshot__date">{ day.date }</span>
							) }
							{ day.sviatok && (
								<div className="farnost-rozpis-snapshot__sviatok">{ day.sviatok }</div>
							) }
						</div>
						{ omse.length === 0 ? (
							<div className="farnost-rozpis-snapshot__empty">
								{ __( 'Sv. omša nie je', 'farnost-plugin' ) }
							</div>
						) : (
							<ul className="farnost-rozpis-snapshot__list">
								{ omse.map( ( m, i ) => (
									<li key={ i }>
										<span className="farnost-rozpis-snapshot__time">{ m.time }</span>
										{ m.oznacenie && (
											<span className="farnost-rozpis-snapshot__oznacenie"> · { m.oznacenie }</span>
										) }
										{ m.umysel && (
											<div className="farnost-rozpis-snapshot__umysel">{ m.umysel }</div>
										) }
									</li>
								) ) }
							</ul>
						) }
					</div>
				);
			} ) }
		</div>
	);
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
		tyzdenOd: { type: 'string', default: '' },
		tyzdenDo: { type: 'string', default: '' },
		dni: { type: 'array', default: [] },
	},
	edit: Edit,
	save: Save,
} );
