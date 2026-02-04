const { createHigherOrderComponent } = wp.compose;
const { InspectorControls } = wp.blockEditor;
const { Button, PanelBody, TextControl, Spinner } = wp.components;
const { Fragment, useState } = wp.element;
const { select, dispatch } = wp.data;
const { __ } = wp.i18n;

const withAltButton = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        if (props.name !== 'core/image') {
            return <BlockEdit {...props} />;
        }

        const { attributes, setAttributes, clientId } = props;
        const [isGenerating, setIsGenerating] = useState(false);
        const [isGeneratingCaption, setIsGeneratingCaption] = useState(false);

        const generateAI = async (type) => {
            const isAlt = type === 'alt';
            if (isAlt) setIsGenerating(true); else setIsGeneratingCaption(true);

            try {
                // 1. Get Context
                const blocks = select('core/block-editor').getBlocks();
                const currentBlockIndex = select('core/block-editor').getBlockIndex(clientId);

                let context = '';

                // Get previous block text
                if (currentBlockIndex > 0) {
                    const prevBlock = blocks[currentBlockIndex - 1];
                    if (prevBlock.attributes.content) { // Assumes paragraph-like blocks
                        context += prevBlock.attributes.content + ' ';
                    }
                }

                // Get next block text
                if (currentBlockIndex < blocks.length - 1) {
                    const nextBlock = blocks[currentBlockIndex + 1];
                    if (nextBlock.attributes.content) {
                        context += nextBlock.attributes.content;
                    }
                }

                // Clean HTML tags
                const div = document.createElement("div");
                div.innerHTML = context;
                const plainContext = div.textContent || div.innerText || "";

                // 2. Call API
                const postId = select('core/editor').getCurrentPostId();

                // Using wp.apiFetch
                const restResponse = await wp.apiFetch({
                    path: '/satr/v1/generate-alt',
                    method: 'POST',
                    data: {
                        imageId: attributes.id,
                        postId: postId,
                        currentAlt: isAlt ? attributes.alt : attributes.caption,
                        context: plainContext,
                        type: isAlt ? 'alt' : 'description'
                    },
                });

                if (restResponse.text) {
                    if (isAlt) {
                        setAttributes({ alt: restResponse.text });
                    } else {
                        setAttributes({ caption: restResponse.text });
                    }

                    dispatch('core/notices').createNotice(
                        'success',
                        (isAlt ? 'Alt text' : 'Caption') + ' generated successfully!',
                        { isDismissible: true, type: 'snackbar' }
                    );
                }

            } catch (error) {
                console.error(error);
                dispatch('core/notices').createNotice(
                    'error',
                    'Error generating: ' + (error.message || 'Unknown error'),
                    { isDismissible: true, type: 'snackbar' }
                );
            } finally {
                if (isAlt) setIsGenerating(false); else setIsGeneratingCaption(false);
            }
        };

        return (
            <Fragment>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody title={__('AI Alt Text & Caption', 'simple-alt-text-rewriter')} initialOpen={true}>
                        <p>{__('Generate SEO-optimized text based on context.', 'simple-alt-text-rewriter')}</p>

                        <Button
                            isPrimary
                            onClick={() => generateAI('alt')}
                            isBusy={isGenerating}
                            disabled={isGenerating || isGeneratingCaption}
                            style={{ marginBottom: '10px', width: '100%', justifyContent: 'center' }}
                        >
                            {isGenerating ? __('Generating Alt...', 'simple-alt-text-rewriter') : __('Generate Alt Text', 'simple-alt-text-rewriter')}
                        </Button>

                        <Button
                            isSecondary
                            onClick={() => generateAI('caption')}
                            isBusy={isGeneratingCaption}
                            disabled={isGenerating || isGeneratingCaption}
                            style={{ width: '100%', justifyContent: 'center' }}
                        >
                            {isGeneratingCaption ? __('Generating Caption...', 'simple-alt-text-rewriter') : __('Generate Caption', 'simple-alt-text-rewriter')}
                        </Button>
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'withAltButton');

wp.hooks.addFilter(
    'editor.BlockEdit',
    'simple-alt-text-rewriter/with-alt-button',
    withAltButton
);

// Media Library Integration
jQuery(document).ready(function ($) {
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        return;
    }

    var AttachmentDetails = wp.media.view.Attachment.Details;

    if (AttachmentDetails) {
        var originalRender = AttachmentDetails.prototype.render;

        AttachmentDetails.prototype.render = function () {
            originalRender.apply(this, arguments);

            // Alt Button
            if (!this.$el.find('.satr-generate-btn-alt').length) {
                var $buttonAlt = $('<button type="button" class="button satr-generate-btn-alt" style="margin-top: 10px;">' +
                    wp.i18n.__('Generate Alt with AI', 'simple-alt-text-rewriter') + '</button>');

                var view = this;
                $buttonAlt.on('click', function (e) {
                    satr_generate_meta(e, view, $(this), 'alt');
                });
                this.$el.find('.setting[data-setting="alt"]').append($buttonAlt);
            }

            // Description Button - Check if setting exists
            // The description setting selector depends on version, usually .setting[data-setting="description"]
            if (this.$el.find('.setting[data-setting="description"]').length && !this.$el.find('.satr-generate-btn-desc').length) {
                var $buttonDesc = $('<button type="button" class="button satr-generate-btn-desc" style="margin-top: 10px;">' +
                    wp.i18n.__('Generate Description with AI', 'simple-alt-text-rewriter') + '</button>');

                var view = this;
                $buttonDesc.on('click', function (e) {
                    satr_generate_meta(e, view, $(this), 'description');
                });
                this.$el.find('.setting[data-setting="description"]').append($buttonDesc);
            }

            return this;
        };

        function satr_generate_meta(e, view, $btn, type) {
            e.preventDefault();
            var model = view.model;

            $btn.prop('disabled', true).text(wp.i18n.__('Generating...', 'simple-alt-text-rewriter'));
            var uploadedTo = model.get('uploadedTo') || 0;
            var currentText = (type === 'alt') ? model.get('alt') : model.get('description');

            wp.apiFetch({
                path: '/satr/v1/generate-alt',
                method: 'POST',
                data: {
                    imageId: model.get('id'),
                    postId: uploadedTo,
                    currentAlt: currentText, // reusing param name for simplicity or we can update API to accept generic
                    type: type,
                    context: ''
                },
            }).then(function (res) {
                if (res.text) {
                    if (type === 'alt') {
                        model.set('alt', res.text);
                        // Force update UI
                        view.$el.find('.setting[data-setting="alt"] textarea').val(res.text);
                    } else {
                        model.set('description', res.text);
                        // Force update UI
                        view.$el.find('.setting[data-setting="description"] textarea').val(res.text);
                    }
                    model.save();
                    $btn.text(wp.i18n.__('Refreshed!', 'simple-alt-text-rewriter'));
                    setTimeout(function () {
                        $btn.prop('disabled', false).text(
                            type === 'alt' ? wp.i18n.__('Generate Alt with AI', 'simple-alt-text-rewriter') : wp.i18n.__('Generate Description with AI', 'simple-alt-text-rewriter')
                        );
                    }, 2000);
                }
            }).catch(function (err) {
                console.error(err);
                $btn.text(wp.i18n.__('Error', 'simple-alt-text-rewriter'));
                setTimeout(function () {
                    $btn.prop('disabled', false).text(
                        type === 'alt' ? wp.i18n.__('Generate Alt with AI', 'simple-alt-text-rewriter') : wp.i18n.__('Generate Description with AI', 'simple-alt-text-rewriter')
                    );
                }, 2000);
            });
        }
    }
});
