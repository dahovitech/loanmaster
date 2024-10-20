document.addEventListener('DOMContentLoaded', () => {
    // Sélection des boutons et des URLs nécessaires
    const buttonAll = document.querySelector('button[id="generate-all"]');
    const buttonsSingle = document.querySelectorAll(".generate");
    const urlGetEntity = document.querySelector('#get-entity')?.dataset.url;
    const urlTranslateText = document.querySelector('#translate-text')?.dataset.url;
    const urlTranslateAllSave = document.querySelector('#translate-all-save')?.dataset.url;
    const urlTranslateSave = document.querySelector('#translate-save')?.dataset.url;
    const entityId = document.querySelector('#entity-id')?.dataset.url;
    const defaultLanguage = document.querySelector('#default-language')?.dataset.url;
    const entityName = document.querySelector('#entity-name')?.dataset.url;

    // Vérification de l'existence des éléments nécessaires
    if (!buttonAll || !buttonsSingle || !urlGetEntity || !urlTranslateText || !urlTranslateAllSave || !urlTranslateSave || !entityId || !defaultLanguage || !entityName) {
        console.error('One or more required elements are missing.');
        return;
    }

    // Gestionnaire d'événements pour le bouton de traduction unique
    buttonsSingle.forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            button.disabled = true;
            button.textContent = 'Loading...';

            const locale = button.getAttribute('data-locale');
            const field = button.getAttribute('data-field');
            const content = button.getAttribute('data-content');

            try {
                const translationRequest = await fetch(urlTranslateText, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ text: content, locale: locale, source: defaultLanguage })
                });

                const translation = await translationRequest.json();
                const translations = [{
                    entityName: entityName,
                    locale: locale,
                    field: field,
                    content: translation
                }];

                await axios.post(urlTranslateSave, {
                    'translations': translations,
                    'entityId': entityId,
                    'entityName': entityName
                });

                swal("Success", "Translation saved successfully", "success");
            } catch (error) {
                swal("Error", "An error occurred while updating the entity", "error");
            } finally {
                button.disabled = false;
                button.textContent = 'Generate';
            }
        });
    });

    // Gestionnaire d'événements pour le bouton de traduction de tous les champs
    buttonAll.addEventListener('click', async (e) => {
        e.preventDefault();
        buttonAll.disabled = true;
        buttonAll.textContent = 'Loading...';

        try {
            const response = await axios.get(urlGetEntity, {
                params: {
                    id: entityId
                }
            });
            const { languages, defaultLanguage, fields, entity } = response.data;
            const translationRequests = [];

            for (const language of languages) {
                for (const field of fields) {
                    const fieldValue = entity[field];
                    if (fieldValue) {
                        const translationRequest = fetch(urlTranslateText, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ text: fieldValue, locale: language.code, source: defaultLanguage })
                        }).then(response => response.json()).then(translation => ({
                            locale: language.code,
                            field: field,
                            content: translation
                        }));

                        translationRequests.push(translationRequest);
                    }
                }
            }

            const translations = await Promise.all(translationRequests);

            await axios.post(urlTranslateAllSave, {
                'translations': translations,
                'entityId': entityId,
                'entityName': entityName
            });

            swal("Success", "Entity updated and translations saved successfully", "success");
        } catch (error) {
            swal("Error", "An error occurred while updating the entity", "error");
        } finally {
            buttonAll.disabled = false;
            buttonAll.textContent = 'Regenerate all';
        }
    });
});
