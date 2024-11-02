let swiper;

function createFigureElement(image, index, form) {
    const figure = document.createElement("figure");
    figure.className = "custom-col";

    const imgElement = document.createElement("img");
    imgElement.src = image.url;
    imgElement.className = "aig-image";
    imgElement.alt = "Generated Image " + (index + 1);
    figure.appendChild(imgElement);

    const figCaption = document.createElement("figcaption");
    const downloadButton = document.createElement("button");
    downloadButton.setAttribute('type', 'button');
    downloadButton.className = "aig-download-button";

    const label = form.getAttribute("data-download") === "manual" ? 'Download Image ' + (index + 1) : 'Use Image ' + (index + 1) + ' as profile picture';
    downloadButton.innerHTML = '<span class="dashicons dashicons-download"></span> ' + label;
    figCaption.appendChild(downloadButton);

    figure.appendChild(figCaption);

    // Gérer le clic sur le bouton de téléchargement
    handleDownloadButtonClick(downloadButton, image, index, form);

    return figure;
}

function handleDownloadButtonClick(button, image, index, form) {
    button.addEventListener("click", function () {
        if (form.getAttribute("data-download") !== "wp_avatar") {
            const link = document.createElement("a");
            link.href = image.url;
            link.target = '_blank';
            link.download = "image" + (index + 1) + ".png";
            link.style.display = "none";

            form.appendChild(link);
            link.click();
            form.removeChild(link);
        } else {
            fetch(ajaxurl, {
                method: "POST",
                body: new URLSearchParams({
                    action: "change_wp_avatar",
                    image_url: image.url,
                }),
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "Cache-Control": "no-cache",
                },
            })
                .then((response) => response.json())
                .then((result) => {
                    if (confirm("You have successfully changed your profile picture.")) {
                        window.location.reload();
                    }
                })
                .catch((error) => {
                    console.error("Error API request :", error);
                });
        }
    });
}

function insertFigureElement(container, figure) {
    if (container.firstChild) {
        container.insertBefore(figure, container.firstChild);
    } else {
        container.appendChild(figure);
    }
}

function storeImagesLocally(newImages) {
    const storedImages = retrieveStoredImages();
    const currentTime = Date.now();

    const imagesWithTimestamp = newImages.map(image => ({
        ...image,
        timestamp: currentTime
    }));

    const combinedImages = [...storedImages, ...imagesWithTimestamp];
    const validImages = removeExpiredImages(combinedImages);

    localStorage.setItem('aig-local-generated-images', JSON.stringify(validImages));
}

function removeExpiredImages(images) {
    const oneHour = 60 * 60 * 1000;
    const currentTime = Date.now();
    return images.filter(image => (currentTime - image.timestamp) <= oneHour);
}

function retrieveStoredImages() {
    const storedImages = localStorage.getItem('aig-local-generated-images');
    if (!storedImages) return [];

    let images = JSON.parse(storedImages);
    images = removeExpiredImages(images);
    localStorage.setItem('aig-local-generated-images', JSON.stringify(images));
    return images;
}

function initializeSwiper(swiper, form) {
    let $figures = form.querySelectorAll('.aig-results figure');

    if ($figures.length > 0) {
        const aigResults = form.querySelector('.aig-results');
        const swiperWrapper = document.createElement('div');
        swiperWrapper.className = 'swiper-wrapper';

        $figures.forEach(figure => {
            figure.classList.add('swiper-slide');
            swiperWrapper.appendChild(figure);
        });

        aigResults.innerHTML = '';
        aigResults.appendChild(swiperWrapper);

        const swiperPagination = document.createElement('div');
        swiperPagination.className = 'swiper-pagination';
        aigResults.appendChild(swiperPagination);

        if (swiper && aigResults.classList.contains('swiper-initialized')) {
            swiper.update();
        } else {
            swiper = new Swiper(form.querySelector('.aig-results'), {
                direction: 'horizontal',
                slidesPerView: 'auto',
                autoWidth: true,
                spaceBetween: 0,
                loop: false,
                pagination: {
                    el: '.swiper-pagination',
                },
            });
        }
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll(".aig-form");

    if (forms.length) {
        forms.forEach((form, formIndex) => {
            form.setAttribute("data-index", formIndex+1);

            if (forms.length > 1) {
                const FORM_TITLE_CLASS = 'aig-form-title';
                const ICON_DOWN = '- ';
                const ICON_RIGHT = '+ ';

                const updateIcon = (element, content, text) => {
                    const icon = content.is(':visible') ? ICON_DOWN : ICON_RIGHT;
                    element.firstChild.textContent = icon + text;
                };

                const formToggleLabel = form.getAttribute("data-toggle-label");
                const formContainer = form.closest('.aig-form-container');
                const formTitleText = formToggleLabel + (formIndex + 1);
                const formTitleElement = document.createElement('div');
                formTitleElement.className = FORM_TITLE_CLASS;
                formTitleElement.innerText = ICON_RIGHT + formTitleText;

                formTitleElement.addEventListener('click', () => {
                    const content = jQuery(formTitleElement.nextElementSibling);
                    content.toggle();
                    updateIcon(formTitleElement, content, formTitleText);
                });

                formContainer.insertAdjacentElement('afterbegin', formTitleElement);

                // Initialize the display of the icon
                if (formIndex > 0) {
                    jQuery(formTitleElement.nextElementSibling).hide();
                }
                updateIcon(formTitleElement, jQuery(formTitleElement.nextElementSibling), formTitleText);
            }

            form.addEventListener("submit", async function (e) {
                e.preventDefault();

                const publicPrompt = form.querySelector("textarea[name='aig_public_prompt']").value;
                const topicCheckboxes = form.querySelectorAll("input[name='aig_topics[]']:checked");
                const topics = Array.from(topicCheckboxes).map(input => input.value).join(",");
                const promptInput = form.querySelector("input[name='aig_prompt']");
                const prompt = promptInput ? promptInput.value : "";
                const promptWithValues = prompt
                    .replace("{public_prompt}", publicPrompt)
                    .replace("{topics}", topics);

                const container = form.parentElement;
                const containerResults = form.querySelector(".aig-results");
                //containerResults.innerHTML = '';

                // remove previous overlay
                const previousOverlay = form.querySelector(".aig-overlay");
                if (previousOverlay) {
                    previousOverlay.remove();
                }

                const overlay = document.createElement("div");
                overlay.className = "aig-overlay";
                container.appendChild(overlay);

                const loadingAnimation = document.createElement("div");
                loadingAnimation.className = "aig-loading-animation";
                overlay.appendChild(loadingAnimation);

                // Check if there is an image
                const userImgFile = form.querySelector("input[name='aig_public_user_img']");
                let hasFile = false;
                let file = null;
                if (userImgFile && form.getAttribute("data-model") === 'aig-model') {
                    const validImgFormats = ['image/jpeg', 'image/png', 'image/webp'];
                    file = userImgFile.files[0];

                    if (!file) {
                        alert("Please upload an image.");
                        overlay.remove();
                        return;
                    }

                    if (!validImgFormats.includes(file.type)) {
                        alert('Format is not supported. Supported formats : jpeg, png, webp.');
                        overlay.remove();
                        return;
                    }

                    hasFile = true;
                }
                
                let data = {
                    id: form.getAttribute("data-id"),
                    action: form.getAttribute("data-action"),
                    _ajax_nonce: form.querySelector("input[name='_ajax_nonce']").value,
                    generate: 1,
                    user_limit: form.querySelector("input[name='user_limit']").value,
                    user_limit_duration: form.querySelector("input[name='user_limit_duration']").value,
                    n: form.getAttribute("data-n"),
                    size: form.getAttribute("data-size"),
                    model: form.getAttribute("data-model"),
                    style: form.getAttribute("data-style"),
                    quality: form.getAttribute("data-quality"),
                    download: form.getAttribute("data-download"),
                    prompt: promptWithValues,
                    public_prompt: publicPrompt,
                    topics: topics
                };

                if (hasFile) {
                    let img = new Image();
                    img.onload = async function () {
                        if (img.width < 64 || img.height < 64) {
                            alert('Image must be greater than 64x64 pixels.');
                            overlay.remove();
                            return;
                        }

                        const reader = new FileReader();
                        reader.onloadend = async function () {
                            const base64Image = reader.result.split(',')[1];
                            data.user_img = base64Image;
                            await sendRequests(data, form);
                        }

                        reader.onerror = function() {
                            alert('Error when read image.');
                            overlay.remove();
                            return;
                        };

                        reader.readAsDataURL(file);
                    }

                    img.onerror = function() {
                        alert('The image cannot be read. Please upload a valid image.');
                        overlay.remove();
                        return;
                    };

                    img.src = URL.createObjectURL(file);
                }
                else {
                    await sendRequests(data, form);
                }

                async function sendRequests(data, form) {
                    const ajaxurl = form.getAttribute("action");

                    let requests = [];
                    if ((data.model === 'dall-e-3' || data.model === 'aig-model') && data.n > 1) {
                        requests = Array.from({ length: data.n }, () => data);
                    } else {
                        requests.push(data);
                    }
                    
                    let responses = [];
                    for (let i = 0; i < requests.length; i++) {
                        const requestData = requests[i];
                        const response = await fetch(ajaxurl, {
                            method: "POST",
                            body: new URLSearchParams(requestData),
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                                "Cache-Control": "no-cache",
                            },
                        });
                
                        const json = await response.json();
                        responses.push(json);
                    
                        // Add a timeout between each request
                        if (i < requests.length - 1) {
                            await new Promise(resolve => setTimeout(resolve, 200));
                        }
                    }
                        
                    try {
                        // Merge all responses
                        const mergedResponse = responses.reduce((acc, response) => {
                            if (response.error && response.error.message) {
                                if (response.error.product_url) {
                                    // Utilisez une expression régulière pour extraire le texte entre Link
                                    const linkTextMatch = response.error.message.match(/\[Link\]\((.*?)\)/);
                                    if (linkTextMatch && linkTextMatch[1]) {
                                        const linkText = linkTextMatch[1];
                                        // Remplacez Link par un lien HTML avec le texte extrait
                                        response.error.message = response.error.message.replace(
                                            `[Link](${linkText})`,
                                            `<a href="${response.error.product_url}">${linkText}</a>`
                                        );
                                    }
                                }
                                acc.errors.push(response.error.message);
                            }
                            if (response.images && response.images.length > 0) {
                                acc.images = acc.images.concat(response.images);
                            }
                            if (response.user_balance !== undefined) {
                                acc.user_balances.push(String(response.user_balance));
                            }
                            return acc;
                        }, { images: [], errors: [], user_balances: [] });
                
                        overlay.style.display = "none";
                        form.querySelector(".aig-results-separator").style.display = 'block';

                        if (mergedResponse.images && mergedResponse.images.length > 0) {
                            mergedResponse.images.forEach((image, index) => {
                                const figure = createFigureElement(image, index, form);
                                insertFigureElement(containerResults, figure);
                            });

                            // Store images générées localy
                            storeImagesLocally(mergedResponse.images);
                        }

                        if (mergedResponse.errors && mergedResponse.errors.length > 0) {
                            const errorContainer = form.querySelector(".aig-errors");
                            const uniqueErrors = [...new Set(mergedResponse.errors)];
                            errorContainer.innerHTML = uniqueErrors.join('<br>');
                        }

                        const userBalanceValueElements = document.querySelectorAll('.aig-credits-balance-value');
                        if (userBalanceValueElements.length > 0) {
                            //console.log(mergedResponse.user_balances);
                            if (Array.isArray(mergedResponse.user_balances) && mergedResponse.user_balances.length > 0) {
                                const numericBalances = mergedResponse.user_balances.map(Number);
                                
                                const minBalance = Math.min(...numericBalances);
                                userBalanceValueElements.forEach(element => {
                                    element.innerHTML = minBalance;
                                });
                            } else {
                                userBalanceValueElements.forEach(element => {
                                    element.innerHTML = 0;
                                });
                            }
                        }

                        initializeSwiper(swiper, form);
                
                    } catch (error) {
                        console.error("API Request Error :", error);
                    }
                }
            });

            const storedImages = retrieveStoredImages();
            const clearButton = form.querySelector('.aig-clear-button');

            if (storedImages.length > 0) {
                const insertImagePromises = storedImages.map((image, index) => {
                    return new Promise((resolve) => {
                        const figure = createFigureElement(image, index, form);
                        setTimeout(() => {
                            insertFigureElement(form.querySelector(".aig-results"), figure);
                            resolve();
                        }, 500);
                    });
                });
            
                Promise.all(insertImagePromises).then(() => {
                    form.querySelector(".aig-results-separator").style.display = 'block';
                    initializeSwiper(swiper, form);
                });

                clearButton.style.display = 'block';
                clearButton.addEventListener('click', function(event) {
                    event.preventDefault();
                    const confirmation = confirm(this.getAttribute('data-confirm'));
                    if (confirmation) {
                        localStorage.removeItem('aig-local-generated-images');
                        location.reload();
                    }
                });
            }
            else {
                clearButton.style.display = 'none';
            }
            
        });
    }
});