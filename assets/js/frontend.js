/**
 * Dog Boarding Administrative Software - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    var DBAS = {
        
        init: function() {
            this.bindEvents();
            this.initValidation();
            this.initFileUploads();
            this.initDogManagement();
            this.initUserProfile();
        },
        
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.dbas-tab-link', this.handleTabSwitch);
            
            // Form submissions
            $(document).on('submit', '#dbas-add-dog-form', this.handleAddDogForm);
            $(document).on('submit', '#dbas-edit-dog-form', this.handleEditDogForm);
            
            // Delete dog
            $(document).on('click', '.dbas-delete-dog', this.handleDeleteDog);
            
            // Edit dog
            $(document).on('click', '.dbas-edit-dog', this.handleEditDog);
            
            // Modal controls
            $(document).on('click', '.dbas-close', this.closeModal);
            $(document).on('click', '#dbas-show-add-dog-form', this.showAddDogForm);
            
            // Real-time validation
            $(document).on('blur', '.dbas-validate', this.validateField);
            
            // Username/Email availability check
            $(document).on('blur', '#username', this.checkUsernameAvailability);
            $(document).on('blur', '#email', this.checkEmailAvailability);
        },
        
        initValidation: function() {
            // Add validation classes to required fields
            $('input[required], select[required], textarea[required]').addClass('dbas-validate');
        },
        
        initFileUploads: function() {
            // Initialize file upload areas
            $('.dbas-file-upload').each(function() {
                var $this = $(this);
                var $input = $this.find('input[type="file"]');
                var $preview = $this.find('.file-preview');
                
                $input.on('change', function(e) {
                    var file = e.target.files[0];
                    if (file) {
                        DBAS.previewFile(file, $preview);
                    }
                });
            });
        },
        
        initDogManagement: function() {
            // Initialize dog management features
            this.loadUserDogs();
        },
        
        initUserProfile: function() {
            // Initialize user profile features
            // Emergency contacts
            $(document).on('click', '#dbas-add-emergency-contact', this.addEmergencyContact);
            $(document).on('click', '.dbas-remove-contact', this.removeEmergencyContact);
        },
        
        handleTabSwitch: function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            $('.dbas-tab-link').removeClass('active');
            $(this).addClass('active');
            
            $('.dbas-tab-content').removeClass('active').hide();
            $(target).addClass('active').fadeIn();
        },
        
        handleAddDogForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            
            // Show loading
            DBAS.showLoading($form);
            
            $.ajax({
                url: dbas_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    DBAS.hideLoading($form);
                    
                    if (response.success) {
                        DBAS.showMessage('success', response.data.message);
                        $form[0].reset();
                        DBAS.loadUserDogs(); // Reload dogs list
                        
                        // Switch to dogs tab
                        $('.dbas-tab-link[href="#dbas-dogs"]').trigger('click');
                    } else {
                        DBAS.showMessage('error', response.data.message);
                    }
                },
                error: function() {
                    DBAS.hideLoading($form);
                    DBAS.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        },
        
        handleEditDogForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            formData.append('action', 'dbas_update_dog');
            formData.append('nonce', dbas_ajax.nonce);
            
            DBAS.showLoading($form);
            
            $.ajax({
                url: dbas_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    DBAS.hideLoading($form);
                    
                    if (response.success) {
                        DBAS.showMessage('success', response.data.message);
                        DBAS.closeModal();
                        DBAS.loadUserDogs();
                    } else {
                        DBAS.showMessage('error', response.data.message);
                    }
                },
                error: function() {
                    DBAS.hideLoading($form);
                    DBAS.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        },
        
        handleDeleteDog: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this dog? This action cannot be undone.')) {
                return;
            }
            
            var dogId = $(this).data('dog-id');
            
            $.post(dbas_ajax.ajax_url, {
                action: 'dbas_delete_dog',
                dog_id: dogId,
                nonce: dbas_ajax.nonce
            }, function(response) {
                if (response.success) {
                    DBAS.showMessage('success', response.data.message);
                    DBAS.loadUserDogs();
                } else {
                    DBAS.showMessage('error', response.data.message);
                }
            });
        },
        
        handleEditDog: function(e) {
            e.preventDefault();
            
            var dogId = $(this).data('dog-id');
            
            $.post(dbas_ajax.ajax_url, {
                action: 'dbas_get_dog',
                dog_id: dogId,
                nonce: dbas_ajax.nonce
            }, function(response) {
                if (response.success) {
                    DBAS.populateEditForm(response.data);
                    DBAS.openModal('#dbas-edit-dog-modal');
                } else {
                    DBAS.showMessage('error', response.data.message);
                }
            });
        },
        
        populateEditForm: function(dogData) {
            var $form = $('#dbas-edit-dog-form');
            
            // Build form HTML
            var formHtml = '<input type="hidden" name="dog_id" value="' + dogData.id + '">';
            formHtml += '<input type="hidden" name="owner_id" value="' + dogData.owner_id + '">';
            
            // Add form fields
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_name">Name</label>';
            formHtml += '<input type="text" id="edit_name" name="name" value="' + dogData.name + '" required>';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_breed">Breed</label>';
            formHtml += '<input type="text" id="edit_breed" name="breed" value="' + (dogData.breed || '') + '">';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_birthdate">Birthdate</label>';
            formHtml += '<input type="date" id="edit_birthdate" name="birthdate" value="' + (dogData.birthdate || '') + '">';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label>Sex</label>';
            formHtml += '<div class="dbas-radio-group">';
            formHtml += '<label><input type="radio" name="sex" value="male"' + (dogData.sex === 'male' ? ' checked' : '') + '> Male</label>';
            formHtml += '<label><input type="radio" name="sex" value="female"' + (dogData.sex === 'female' ? ' checked' : '') + '> Female</label>';
            formHtml += '</div>';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_color">Color</label>';
            formHtml += '<input type="text" id="edit_color" name="color" value="' + (dogData.color || '') + '">';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label>Spayed/Neutered</label>';
            formHtml += '<div class="dbas-radio-group">';
            formHtml += '<label><input type="radio" name="spay_neuter" value="yes"' + (dogData.spay_neuter === 'yes' ? ' checked' : '') + '> Yes</label>';
            formHtml += '<label><input type="radio" name="spay_neuter" value="no"' + (dogData.spay_neuter === 'no' ? ' checked' : '') + '> No</label>';
            formHtml += '</div>';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_vet_details">Veterinarian Details</label>';
            formHtml += '<textarea id="edit_vet_details" name="vet_details" rows="3">' + (dogData.vet_details || '') + '</textarea>';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_allergies">Allergies</label>';
            formHtml += '<textarea id="edit_allergies" name="allergies" rows="3">' + (dogData.allergies || '') + '</textarea>';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-group">';
            formHtml += '<label for="edit_behavioral_notes">Behavioral Notes</label>';
            formHtml += '<textarea id="edit_behavioral_notes" name="behavioral_notes" rows="3">' + (dogData.behavioral_notes || '') + '</textarea>';
            formHtml += '</div>';
            
            formHtml += '<div class="dbas-form-submit">';
            formHtml += '<button type="submit" class="button button-primary">Update Dog</button>';
            formHtml += '<button type="button" class="button dbas-cancel">Cancel</button>';
            formHtml += '</div>';
            
            $form.html(formHtml);
        },
        
        loadUserDogs: function() {
            $.post(dbas_ajax.ajax_url, {
                action: 'dbas_get_user_dogs',
                nonce: dbas_ajax.nonce
            }, function(response) {
                if (response.success) {
                    DBAS.renderDogsList(response.data);
                }
            });
        },
        
        renderDogsList: function(dogs) {
            var $container = $('.dbas-dogs-list tbody');
            if ($container.length === 0) return;
            
            $container.empty();
            
            if (dogs.length === 0) {
                $container.append('<tr><td colspan="4">No dogs registered yet.</td></tr>');
                return;
            }
            
            dogs.forEach(function(dog) {
                var row = '<tr>';
                row += '<td>' + dog.name + '</td>';
                row += '<td>' + (dog.breed || 'N/A') + '</td>';
                row += '<td><span class="dbas-status-' + dog.status + '">' + dog.status.charAt(0).toUpperCase() + dog.status.slice(1) + '</span></td>';
                row += '<td>';
                row += '<button class="button dbas-edit-dog" data-dog-id="' + dog.id + '">Edit</button> ';
                row += '<button class="button dbas-delete-dog" data-dog-id="' + dog.id + '">Delete</button>';
                row += '</td>';
                row += '</tr>';
                $container.append(row);
            });
        },
        
        validateField: function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            var fieldValue = $field.val();
            
            if (!fieldValue && $field.prop('required')) {
                DBAS.showFieldError($field, 'This field is required');
                return false;
            }
            
            // Email validation
            if ($field.attr('type') === 'email' && fieldValue) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(fieldValue)) {
                    DBAS.showFieldError($field, 'Please enter a valid email address');
                    return false;
                }
            }
            
            // Phone validation
            if (fieldName === 'dbas_phone' && fieldValue) {
                var phoneRegex = /^[\d\s\-\+\(\)]+$/;
                if (!phoneRegex.test(fieldValue)) {
                    DBAS.showFieldError($field, 'Please enter a valid phone number');
                    return false;
                }
            }
            
            // Clear error if valid
            DBAS.clearFieldError($field);
            return true;
        },
        
        checkUsernameAvailability: function() {
            var username = $(this).val();
            if (!username) return;
            
            $.post(dbas_ajax.ajax_url, {
                action: 'dbas_check_username',
                username: username,
                nonce: dbas_ajax.nonce
            }, function(response) {
                if (response.success) {
                    DBAS.showFieldSuccess($('#username'), response.data.message);
                } else {
                    DBAS.showFieldError($('#username'), response.data.message);
                }
            });
        },
        
        checkEmailAvailability: function() {
            var email = $(this).val();
            if (!email) return;
            
            $.post(dbas_ajax.ajax_url, {
                action: 'dbas_check_email',
                email: email,
                nonce: dbas_ajax.nonce
            }, function(response) {
                if (response.success) {
                    DBAS.showFieldSuccess($('#email'), response.data.message);
                } else {
                    DBAS.showFieldError($('#email'), response.data.message);
                }
            });
        },
        
        addEmergencyContact: function() {
            var html = '<div class="dbas-emergency-contact-item">';
            html += '<input type="text" name="dbas_emergency_name[]" placeholder="Name" />';
            html += '<input type="text" name="dbas_emergency_phone[]" placeholder="Phone" />';
            html += '<button type="button" class="button dbas-remove-contact">Remove</button>';
            html += '</div>';
            $('#dbas-emergency-contacts, #dbas-emergency-contacts-frontend').append(html);
        },
        
        removeEmergencyContact: function() {
            $(this).closest('.dbas-emergency-contact-item').fadeOut(function() {
                $(this).remove();
            });
        },
        
        showAddDogForm: function(e) {
            e.preventDefault();
            $('.dbas-tab-link[href="#dbas-add-dog"]').trigger('click');
        },
        
        previewFile: function(file, $preview) {
            if (file.type.match('image.*')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $preview.html('<img src="' + e.target.result + '" style="max-width: 200px;">');
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                $preview.html('<p>PDF file selected: ' + file.name + '</p>');
            }
        },
        
        openModal: function(selector) {
            $(selector).fadeIn();
            $('body').addClass('dbas-modal-open');
        },
        
        closeModal: function() {
            $('.dbas-modal, #dbas-edit-dog-modal').fadeOut();
            $('body').removeClass('dbas-modal-open');
        },
        
        showLoading: function($element) {
            $element.addClass('dbas-loading');
            $element.find('button[type="submit"]').prop('disabled', true).text('Processing...');
        },
        
        hideLoading: function($element) {
            $element.removeClass('dbas-loading');
            $element.find('button[type="submit"]').prop('disabled', false).text('Submit');
        },
        
        showMessage: function(type, message) {
            var $message = $('<div class="dbas-notice dbas-' + type + '"><p>' + message + '</p></div>');
            
            // Insert message at top of content
            if ($('.dbas-portal').length) {
                $('.dbas-portal').prepend($message);
            } else if ($('#dbas-message').length) {
                $('#dbas-message').html($message);
            } else {
                $('body').prepend($message);
            }
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: $message.offset().top - 100
            }, 500);
        },
        
        showFieldError: function($field, message) {
            $field.addClass('error');
            var $error = $('<span class="dbas-field-error">' + message + '</span>');
            $field.after($error);
        },
        
        clearFieldError: function($field) {
            $field.removeClass('error');
            $field.next('.dbas-field-error').remove();
        },
        
        showFieldSuccess: function($field, message) {
            $field.addClass('success');
            var $success = $('<span class="dbas-field-success">' + message + '</span>');
            $field.after($success);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        DBAS.init();
    });
    
    // Handle modal cancel button
    $(document).on('click', '.dbas-cancel', function() {
        DBAS.closeModal();
    });
    
    // Close modal on outside click
    $(document).on('click', '.dbas-modal, #dbas-edit-dog-modal', function(e) {
        if (e.target === this) {
            DBAS.closeModal();
        }
    });
    
})(jQuery);