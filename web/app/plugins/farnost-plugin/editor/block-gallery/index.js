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

// Zjednotené s PHP Gallery::variantFor — pre 3+ fotky vždy 1+2 mosaic.
// 4+ figures sú v DOM ale skryté cez CSS, lightbox cez ne listuje.
function variantFor( count ) {
	if ( count <= 1 ) return 'count-1';
	if ( count === 2 ) return 'count-2';
	return 'count-3';
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

	// Zjednotené s PHP render: pre count-3 variant je overlay „+N" na 3. fotke
	// (index 2), kde overflow = count - 3. Render všetky figures aby boli
	// k dispozícii pre lightbox; 4+ skryté cez CSS.
	const overflow = variant === 'count-3' ? Math.max( 0, images.length - 3 ) : 0;

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

			{ /* Inline editor štýly — add_editor_style cestou má v WP 7 nižšiu
			     specificity než editor core (`.wp-block` reset). Tieto pravidlá
			     forcujú grid layout v iframed canvas-e a zopakujú frontend
			     mosaic 1+2 pattern. Drag-reorder/inline captions sú ďalšia
			     iterácia — teraz aspoň vizuálna konzistencia s frontendom. */ }
			<style>{ `
				.wp-block-farnost-gallery.farnost-gallery {
					display: grid;
					gap: 8px;
					margin: 16px 0;
				}
				.wp-block-farnost-gallery .farnost-gallery__item {
					position: relative;
					margin: 0;
					overflow: hidden;
					background: #d2c4a3;
				}
				.wp-block-farnost-gallery .farnost-gallery__img {
					display: block;
					width: 100%;
					height: 100%;
					object-fit: cover;
				}
				.wp-block-farnost-gallery .farnost-gallery__overlay {
					position: absolute;
					inset: 0;
					display: flex;
					align-items: center;
					justify-content: center;
					background: rgba(28, 22, 16, 0.55);
					color: #fdf8ec;
					font-size: 2rem;
					font-weight: 600;
					backdrop-filter: blur(2px);
				}
				.wp-block-farnost-gallery.farnost-gallery--count-1 .farnost-gallery__item { aspect-ratio: 16 / 9; max-height: 540px; }
				.wp-block-farnost-gallery.farnost-gallery--count-2 { grid-template-columns: 1fr 1fr; }
				.wp-block-farnost-gallery.farnost-gallery--count-2 .farnost-gallery__item { aspect-ratio: 1 / 1; }
				.wp-block-farnost-gallery.farnost-gallery--count-3 {
					grid-template-columns: 2fr 1fr;
					grid-template-rows: 1fr 1fr;
					aspect-ratio: 3 / 2;
				}
				.wp-block-farnost-gallery.farnost-gallery--count-3 .farnost-gallery__item:nth-child(1) {
					grid-row: span 2; aspect-ratio: auto;
				}
				.wp-block-farnost-gallery.farnost-gallery--count-3 .farnost-gallery__item:nth-child(n + 4) {
					display: none;
				}
			` }</style>

			<div { ...blockProps }>
				{ images.map( ( img, i ) => {
					const hasOverlay = variant === 'count-3' && i === 2 && overflow > 0;
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
