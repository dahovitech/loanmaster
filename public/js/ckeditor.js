//js/ckeditor.js

import {
	BalloonEditor,
	
} from 'ckeditor5';

BalloonEditor
    .create(document.querySelector('.editor'))
    .then(editor => {
        const form = editor.sourceElement.closest('form');
        form.addEventListener('submit', event => {
            event.preventDefault();
            const hiddenField = editor.sourceElement.nextElementSibling;
            hiddenField.value = editor.getData();
            form.submit();
        });
    })
    .catch(error => {
        // console.error(error);
    });
