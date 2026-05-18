/**
 * Block `farnost/gallery` — vlastná galéria fotiek.
 *
 * Dynamic block; PHP frontend render je v src/Blocks/Gallery.php.
 *
 * Atribúty:
 *   - images: list<{ id, url, alt, caption, width, height, fullUrl }>
 *   - lightbox: bool (default true) — klik otvorí carousel lightbox
 *   - showCaptions: bool (default false) — captions pod každou fotkou
 *
 * Editor UI (stub):
 *   - Prázdny stav: MediaPlaceholder gallery mode (wp.media picker)
 *   - Naplnený: grid preview podľa count, drag-reorder, inline RichText captions,
 *     Inspector toggle lightbox / show captions.
 *
 * Iterácia 1 (tento commit): registrácia + stub Edit s MediaPlaceholder
 * a basic grid preview. Drag-reorder + inline captions + Inspector — ďalšia iterácia.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, MediaPlaceholder, InspectorControls, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button, PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ALLOWED_TYPES = [ 'image' ];

function variantFor( count ) {
	if ( count <= 1 ) return 'count-1';
	if ( count === 2 ) return 'count-2';
	if ( count === 3 ) return 'count-3';
	if ( count === 4 ) return 'count-4';
	return 'many';
}

/**
 * Normalizuje image object z wp.media na náš shape. wp.media vracia { id, url,
 * alt, caption, width, height, sizes: { large: { url }, full: { url }, ... } }.
 * fullUrl je pre lightbox; url je thumb pre grid.
 */
function normalizeImage( raw ) {
	const sizes = raw.sizes || {};
	const large = sizes.large?.url || sizes.medium_large?.url || raw.url;
	const full  = sizes.full?.url  || raw.url;
	return {
		id:      raw.id,
		url:     large,
		fullUrl: full,
		alt:     raw.alt || '',
		caption: typeof raw.caption === 'string' ? raw.caption : ( raw.caption?.raw || '' ),
		width:   raw.width  || 0,
		height: raw.height || 0,
	};
}

function Edit( { attributes, setAttributes } ) {
	const { images = [], lightbox = true, showCaptions = false } = attributes;
	const variant = variantFor( images.length );
	const blockProps = useBlockProps( {
		className: `farnost-gallery farnost-gallery--${ variant }`,
	} );

	const setImages = ( raws ) => {
		setAttributes( { images: raws.map( normalizeImage ) } );
	};

	if ( ! images.length ) {
		return (
			<div { ...blockProps }>
				<MediaPlaceholder
					labels={ {
						title: __( 'Galéria fotiek', 'farnost-plugin' ),
						instructions: __(
							'Vyber alebo nahraj fotky. Pre 3 fotky sa zobrazia ako 1+2 mosaic, pre 4 ako 2×2, pre 5 a viac sa zobrazia 4 s overlay „+N".',
							'farnost-plugin'
						),
					} }
					icon="format-gallery"
					accept="image/*"
					allowedTypes={ ALLOWED_TYPES }
					multiple
					gallery
					onSelect={ setImages }
				/>
			</div>
		);
	}

	const visible = variant === 'many' ? images.slice( 0, 4 ) : images;
	const overflow = images.length - visible.length;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Galéria', 'farnost-plugin' ) }>
					<ToggleControl
						label={ __( 'Lightbox po kliknutí', 'farnost-plugin' ) }
						help={ __( 'Otvorí veľký náhľad s carouselom a šípkami.', 'farnost-plugin' ) }
						checked={ lightbox }
						onChange={ ( v ) => setAttributes( { lightbox: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Zobraziť popisky', 'farnost-plugin' ) }
						checked={ showCaptions }
						onChange={ ( v ) => setAttributes( { showCaptions: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ visible.map( ( img, i ) => {
					const isLast = i === visible.length - 1;
					const hasOverlay = variant === 'many' && isLast && overflow > 0;
					return (
						<figure
							key={ img.id ?? i }
							className={ `farnost-gallery__item${ hasOverlay ? ' has-overlay' : '' }` }
						>
							<img className="farnost-gallery__img" src={ img.url } alt={ img.alt || '' } />
							{ hasOverlay && (
								<span className="farnost-gallery__overlay">+{ overflow }</span>
							) }
						</figure>
					);
				} ) }
				<div className="farnost-gallery__edit-toolbar" style={ { gridColumn: '1 / -1', marginTop: 8, display: 'flex', gap: 8 } }>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ setImages }
							allowedTypes={ ALLOWED_TYPES }
							multiple
							gallery
							value={ images.map( ( i ) => i.id ).filter( Boolean ) }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open }>
									{ __( 'Upraviť fotky', 'farnost-plugin' ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					<Button variant="tertiary" isDestructive onClick={ () => setAttributes( { images: [] } ) }>
						{ __( 'Odstrániť všetky', 'farnost-plugin' ) }
					</Button>
				</div>
			</div>
		</>
	);
}

registerBlockType( 'farnost/gallery', {
	apiVersion: 3,
	title: __( 'Galéria (Farnosť)', 'farnost-plugin' ),
	description: __( 'Vlastná galéria fotiek s mosaicom a lightboxom.', 'farnost-plugin' ),
	category: 'farnost',
	icon: 'format-gallery',
	supports: { html: false },
	attributes: {
		images:       { type: 'array',   default: [] },
		lightbox:     { type: 'boolean', default: true },
		showCaptions: { type: 'boolean', default: false },
	},
	edit: Edit,
	save: () => null,
} );
