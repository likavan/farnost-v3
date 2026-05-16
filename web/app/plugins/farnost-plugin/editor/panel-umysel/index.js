/**
 * Gutenberg sidebar panel pre CPT `umysel` — úmysel sv. omše.
 *
 * Polia: dátum, čas, kostol (referencia), text úmyslu, anonymný prepínač.
 * Kostoly sa načítavajú cez REST `/wp/v2/kostoly` a renderujú do ComboboxControl.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { TextControl, TextareaControl, ToggleControl, ComboboxControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

function UmyselPanel() {
	const { postType, datum, cas, kostolId, text, anonymny } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const meta = editor.getEditedPostAttribute( 'meta' ) || {};
		return {
			postType: editor.getCurrentPostType(),
			datum: meta.farnost_datum ?? '',
			cas: meta.farnost_cas ?? '',
			kostolId: meta.farnost_kostol_id ?? 0,
			text: meta.farnost_text ?? '',
			anonymny: !! meta.farnost_anonymny,
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	const [ kostolyOptions, setKostolyOptions ] = useState( [] );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wp/v2/kostoly?per_page=100&_fields=id,title' } )
			.then( ( posts ) => {
				if ( cancelled || ! Array.isArray( posts ) ) {
					return;
				}
				setKostolyOptions(
					posts.map( ( p ) => ( {
						value: String( p.id ),
						label: p.title?.rendered || `#${ p.id }`,
					} ) )
				);
			} )
			.catch( () => {
				// pri chybe necháme prázdny zoznam
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	if ( postType !== 'umysel' ) {
		return null;
	}

	const setMeta = ( field, value ) => {
		editPost( { meta: { [ field ]: value } } );
	};

	const setKostol = ( value ) => {
		const id = parseInt( value, 10 ) || 0;
		editPost( { meta: { farnost_kostol_id: id } } );
	};

	return (
		<PluginDocumentSettingPanel
			name="farnost-umysel-detail"
			title={ __( 'Úmysel sv. omše', 'farnost-plugin' ) }
			className="farnost-umysel-panel"
		>
			<div style={ { display: 'flex', gap: 8 } }>
				<div style={ { flex: 1 } }>
					<TextControl
						label={ __( 'Dátum', 'farnost-plugin' ) }
						type="date"
						value={ datum || '' }
						onChange={ ( v ) => setMeta( 'farnost_datum', v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
				<div style={ { width: 110 } }>
					<TextControl
						label={ __( 'Čas', 'farnost-plugin' ) }
						type="time"
						value={ cas || '' }
						onChange={ ( v ) => setMeta( 'farnost_cas', v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
			</div>
			<div style={ { marginTop: 12 } }>
				<ComboboxControl
					label={ __( 'Kostol', 'farnost-plugin' ) }
					value={ kostolId ? String( kostolId ) : '' }
					options={ kostolyOptions }
					onChange={ setKostol }
					allowReset
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>
			<div style={ { marginTop: 12 } }>
				<TextareaControl
					label={ __( 'Text úmyslu', 'farnost-plugin' ) }
					value={ text || '' }
					onChange={ ( v ) => setMeta( 'farnost_text', v ) }
					rows={ 3 }
					placeholder={ __( 'Za zdravie rodiny / † Mária Nováková …', 'farnost-plugin' ) }
					__nextHasNoMarginBottom
				/>
			</div>
			<div style={ { marginTop: 12 } }>
				<ToggleControl
					label={ __( 'Anonymný úmysel', 'farnost-plugin' ) }
					help={ __( 'Skryť mená na verejnom webe', 'farnost-plugin' ) }
					checked={ !! anonymny }
					onChange={ ( v ) => setMeta( 'farnost_anonymny', !! v ) }
					__nextHasNoMarginBottom
				/>
			</div>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'farnost-umysel-panel', {
	render: UmyselPanel,
} );
