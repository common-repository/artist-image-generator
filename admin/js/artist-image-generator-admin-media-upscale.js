jQuery(document).ready(function ($) {
    const UPSCALE_TEXT = 'Upscale';
    const UPSCALING_TEXT = 'Upscaling... (~30s)';
    const ERROR_MESSAGE = 'An error occurred while upscaling the image.';
    const PREMIUM_FEATURE_MESSAGE = "This is an AIG premium feature. Please buy credits here: https://artist-image-generator.com/product/credits/";

    function isValidImage(attributes) {
        const supportedFormats = ['jpeg', 'png', 'webp'];
        const { subtype, width, height } = attributes;
    
        const totalPixels = width * height;
        const aspectRatio = width / height;
    
        return (
            supportedFormats.includes(subtype) &&
            width >= 64 &&
            height >= 64 &&
            totalPixels >= 4096 &&
            totalPixels <= 9437184 &&
            aspectRatio >= 0.4 &&
            aspectRatio <= 2.5
        );
    }

    function createUpscaleButton(postId) {
        const container = document.createElement('div');
        container.style.marginTop = '20px';
    
        const hr = document.createElement('hr');
        container.appendChild(hr);
    
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-small aig-upscale-link';
        button.setAttribute('data-id', postId);
        button.style.width = '100%';
        button.style.marginTop = '5px';
        button.innerHTML = '→ Upscale this image (1 credit)';
    
        container.appendChild(button);
        return container;
    }

    function upscaleImage(e, postId) {
        if (aig_upscale_object.upscale_enabled != "1") {
            alert(PREMIUM_FEATURE_MESSAGE);
            e.preventDefault();
            return false;
        }

        const button = $(`.aig-upscale-link[data-id="${postId}"]`);
        button.text(UPSCALING_TEXT);

        $.ajax({
            url: aig_upscale_object.ajax_url,
            type: 'POST',
            data: {
                action: 'media_upscale_image',
                security: aig_upscale_object.nonce,
                item_id: postId,
            },
            success: function(response) {
                if (response.success) {
                    button.text('Upscale Successful');
                    location.reload();
                } else {
                    alert(response.data);
                    button.text(UPSCALE_TEXT);
                }
            },
            error: function() {
                alert(ERROR_MESSAGE);
                button.text(UPSCALE_TEXT);
            }
        });
    }

    function extendAttachmentDetails(viewClass, templateName) {
        return viewClass.extend({
            AIGUpscaleImage: function(e) {
                const { id } = this.model.attributes;
                
                upscaleImage(e, id);
            },
            events: {
                ...viewClass.prototype.events,
                'click .aig-upscale-link': 'AIGUpscaleImage',
            },
            template: function(view) {
                const html = wp.media.template(templateName)(view);
                const dom = document.createElement('div');
                dom.innerHTML = html;

                const details = dom.querySelector('.details');
                const postId = this.model.attributes.id;

                if (isValidImage(this.model.attributes)) {
                    const upscaleButton = createUpscaleButton(postId);
                    details.appendChild(upscaleButton);
                }

                return dom.innerHTML;
            }
        });
    }

    wp.media.view.Attachment.Details.TwoColumn = extendAttachmentDetails(
        wp.media.view.Attachment.Details.TwoColumn,
        'attachment-details-two-column'
    );

    wp.media.view.Attachment.Details = extendAttachmentDetails(
        wp.media.view.Attachment.Details,
        'attachment-details'
    );
});