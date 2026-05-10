/**
 * Maui Garden Tour Map - JavaScript
 * Uses Google Maps AdvancedMarkerElement API
 */

// Global variables
let map;
let draftMarker = null;
let existingMarkers = [];
let infoWindow = null;
let AdvancedMarkerElement;
let PinElement;
let editMode = false;
let editingMarker = null;
let editingPinId = null;
let userHasPins = false;  // Track if user already has pins on the map
let addressManuallyEdited = false;  // Track if user has manually edited the address
const USER_EMAIL_STORAGE_KEY = 'garden_tour_email';

/**
 * Initialize the Google Map
 * Called after libraries are loaded
 */
async function initMap() {
    const config = window.APP_CONFIG;

    try {
        // Load required libraries
        const { Map, InfoWindow } = await google.maps.importLibrary("maps");
        const markerLib = await google.maps.importLibrary("marker");
        AdvancedMarkerElement = markerLib.AdvancedMarkerElement;
        PinElement = markerLib.PinElement;

        // Map options
        const mapOptions = {
            center: config.mauiCenter,
            zoom: config.defaultZoom,
            mapId: config.mapId,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            mapTypeControl: true,
            mapTypeControlOptions: {
                style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
                position: google.maps.ControlPosition.TOP_LEFT,
                mapTypeIds: [
                    google.maps.MapTypeId.ROADMAP,
                    google.maps.MapTypeId.SATELLITE,
                    google.maps.MapTypeId.HYBRID,
                    google.maps.MapTypeId.TERRAIN
                ]
            },
            zoomControl: true,
            zoomControlOptions: {
                position: google.maps.ControlPosition.RIGHT_CENTER
            },
            streetViewControl: true,
            streetViewControlOptions: {
                position: google.maps.ControlPosition.RIGHT_CENTER
            },
            fullscreenControl: true,
            fullscreenControlOptions: {
                position: google.maps.ControlPosition.RIGHT_TOP
            },
            gestureHandling: 'greedy'
        };

        // Create the map
        map = new Map(document.getElementById('map'), mapOptions);

        // Create shared info window
        infoWindow = new InfoWindow();

        restoreUserEmail();

        // Load existing pins
        loadExistingPins();

        // Add click listener to place new pins
        map.addListener('click', handleMapClick);

        // Initialize UI event handlers
        initUIHandlers();

        // Check for URL parameters (e.g., after confirmation)
        checkUrlParams();

    } catch (error) {
        console.error('Error initializing map:', error);
        document.getElementById('map').innerHTML = 
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;">' +
            '<p>Error loading map. Please check your API key and billing settings.</p></div>';
    }
}

/**
 * Handle click on map to place a new pin
 */
function handleMapClick(event) {
    // If in edit mode, ignore map clicks
    if (editMode) {
        return;
    }
    
    // If form panel is already open, ignore additional clicks
    if (document.getElementById('formPanel').classList.contains('visible')) {
        return;
    }
    
    const position = event.latLng;
    
    // If user already has pins, show confirmation modal
    if (userHasPins) {
        showAddPinConfirmation(position);
        return;
    }

    // Proceed to place the pin
    placeNewPin(position);
}

/**
 * Show confirmation modal before adding another pin
 */
function showAddPinConfirmation(position) {
    // Store position for later use
    window.pendingPinPosition = position;
    showModal('addPinModal');
}

/**
 * Actually place the new pin after confirmation or first-time
 */
function placeNewPin(position) {
    // Remove existing draft marker if any
    if (draftMarker) {
        draftMarker.map = null;
    }

    // Create custom pin element for draft marker (green)
    const pinElement = new PinElement({
        background: '#4caf50',
        borderColor: '#2e7d32',
        glyphColor: 'white',
        scale: 1.2
    });

    // Create new draft marker using AdvancedMarkerElement
    draftMarker = new AdvancedMarkerElement({
        position: position,
        map: map,
        gmpDraggable: true,
        content: pinElement,
        title: 'Drag to adjust position'
    });

    // Update form coordinates when marker is dragged
    draftMarker.addListener('dragend', function() {
        updateFormCoordinates(draftMarker.position);
        // Also update address when dragged to new location
        reverseGeocode(draftMarker.position);
    });

    // Update form coordinates
    updateFormCoordinates(position);
    
    // Get address from coordinates
    reverseGeocode(position);

    // Show the form panel
    showFormPanel();

    // Close instructions banner
    hideInstructions();
}

/**
 * Update form hidden fields with coordinates
 */
function updateFormCoordinates(position) {
    // AdvancedMarkerElement position is a LatLng object or LatLngLiteral
    const lat = typeof position.lat === 'function' ? position.lat() : position.lat;
    const lng = typeof position.lng === 'function' ? position.lng() : position.lng;
    
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
}

/**
 * Reverse geocode coordinates to get address
 */
async function reverseGeocode(position) {
    console.log('reverseGeocode called with position:', position);
    
    // Don't overwrite if user has manually edited the address
    if (addressManuallyEdited) {
        console.log('Address manually edited, skipping geocode');
        return;
    }
    
    const lat = typeof position.lat === 'function' ? position.lat() : position.lat;
    const lng = typeof position.lng === 'function' ? position.lng() : position.lng;
    
    console.log('Geocoding coordinates:', lat, lng);
    
    const addressField = document.getElementById('address');
    
    // Show loading indicator in the field
    addressField.placeholder = 'Looking up address...';
    
    try {
        console.log('Creating Geocoder...');
        const geocoder = new google.maps.Geocoder();
        console.log('Geocoder created, making request...');
        
        const response = await geocoder.geocode({ 
            location: { lat, lng } 
        });
        
        console.log('Geocoder response:', response);
        
        if (response.results && response.results.length > 0) {
            console.log('Found', response.results.length, 'results');
            
            // Find the best result - prefer street address or route
            let bestResult = response.results[0];
            
            for (const result of response.results) {
                const types = result.types || [];
                console.log('Result type:', types, 'Address:', result.formatted_address);
                if (types.includes('street_address') || types.includes('premise')) {
                    bestResult = result;
                    break;
                }
                if (types.includes('route') && !bestResult.types.includes('street_address')) {
                    bestResult = result;
                }
            }
            
            console.log('Best result:', bestResult.formatted_address);
            addressField.value = bestResult.formatted_address;
        } else {
            console.log('No results found');
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        console.error('Error details:', error.message, error.code);
    } finally {
        // Restore placeholder
        addressField.placeholder = 'Street address';
    }
}

/**
 * Load existing confirmed pins from the API
 */
async function loadExistingPins() {
    try {
        const response = await fetch('api/pins.php');
        const data = await response.json();

        if (data.success && data.pins) {
            existingMarkers.forEach(marker => {
                marker.map = null;
            });
            existingMarkers = [];
            userHasPins = false;

            data.pins.forEach(pin => {
                addExistingMarker(pin);
            });
        }
    } catch (error) {
        console.error('Error loading pins:', error);
    }
}

/**
 * Add an existing pin marker to the map
 */
function addExistingMarker(pinData) {
    const userEmail = getUserEmail();
    const isOwner = userEmail && pinData.email && 
                    userEmail.toLowerCase() === pinData.email.toLowerCase();
    
    // Track if user has pins
    if (isOwner) {
        userHasPins = true;
    }
    
    // Create custom pin element - blue for user's pins, red for others
    let pinElement;
    if (isOwner) {
        // User's own pins - blue with star
        pinElement = new PinElement({
            background: '#1976d2',
            borderColor: '#0d47a1',
            glyphColor: 'white',
            glyph: '★',
            scale: 1.1
        });
    } else {
        // Other pins - red
        pinElement = new PinElement({
            background: '#d32f2f',
            borderColor: '#b71c1c',
            glyphColor: 'white'
        });
    }

    const marker = new AdvancedMarkerElement({
        position: { 
            lat: parseFloat(pinData.latitude), 
            lng: parseFloat(pinData.longitude) 
        },
        map: map,
        content: pinElement,
        title: pinData.name || 'Garden Location',
        gmpClickable: true
    });

    // Store pin data with the marker
    marker.pinData = pinData;
    marker.isOwner = isOwner;

    // Add click listener to show info window
    marker.addEventListener('gmp-click', function() {
        showPinInfo(marker);
    });

    existingMarkers.push(marker);
}

/**
 * Show info window for a pin
 */
function showPinInfo(marker) {
    const data = marker.pinData;
    const userEmail = getUserEmail();
    const isOwner = userEmail && data.email && 
                    userEmail.toLowerCase() === data.email.toLowerCase();
    
    // Build info window content
    let content = '<div class="info-window">';
    
    // Image
    if (data.image_path) {
        content += `<div class="info-window-image" style="background-image: url('${escapeHtml(data.image_path)}')"></div>`;
    }
    
    // Name/Title
    if (data.name) {
        content += `<h3 class="info-window-title">${escapeHtml(data.name)}</h3>`;
    } else {
        content += `<h3 class="info-window-title">Garden Location</h3>`;
    }
    
    // Address
    if (data.address) {
        content += `<p class="info-window-address">${escapeHtml(data.address)}</p>`;
    }
    
    // Description
    if (data.description) {
        content += `<p class="info-window-description">${escapeHtml(data.description)}</p>`;
    }
    
    // Email (obfuscated for privacy - showing partial)
    if (data.email) {
        const obfuscatedEmail = obfuscateEmail(data.email);
        content += `<p class="info-window-email">${obfuscatedEmail}</p>`;
    }
    
    // Edit and Delete buttons (only for owner)
    if (isOwner) {
        content += `<div class="info-window-actions">`;
        content += `<button class="info-window-edit-btn" onclick="editPin(${data.id})">Edit</button>`;
        content += `<button class="info-window-delete-btn" onclick="confirmDeletePin(${data.id})">Delete</button>`;
        content += `</div>`;
    }
    
    content += '</div>';

    infoWindow.setContent(content);
    infoWindow.open(map, marker);
}

/**
 * Obfuscate email for display (show first 2 chars and domain)
 */
function obfuscateEmail(email) {
    const parts = email.split('@');
    if (parts.length !== 2) return email;
    
    const name = parts[0];
    const domain = parts[1];
    const visible = name.substring(0, 2);
    const hidden = '*'.repeat(Math.max(name.length - 2, 3));
    
    return `${visible}${hidden}@${domain}`;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getUserEmail() {
    const configEmail = window.APP_CONFIG.userEmail || '';
    if (configEmail) {
        return configEmail;
    }

    try {
        return localStorage.getItem(USER_EMAIL_STORAGE_KEY) || '';
    } catch (error) {
        console.warn('Could not read saved Garden Tour email:', error);
        return '';
    }
}

function saveUserEmail(email) {
    const normalizedEmail = (email || '').trim();
    window.APP_CONFIG.userEmail = normalizedEmail;

    updateReconnectVisibility();

    if (!normalizedEmail) {
        return;
    }

    try {
        localStorage.setItem(USER_EMAIL_STORAGE_KEY, normalizedEmail);
    } catch (error) {
        console.warn('Could not save Garden Tour email:', error);
    }
}

function restoreUserEmail() {
    const email = getUserEmail();
    if (email) {
        saveUserEmail(email);
        console.log('Garden Tour: User email available:', email);
    } else {
        console.log('Garden Tour: User email not set');
    }

    updateReconnectVisibility();
}

function updateReconnectVisibility() {
    const reconnectButton = document.getElementById('reconnectBtn');
    if (!reconnectButton) {
        return;
    }

    reconnectButton.hidden = Boolean(getUserEmail());
}

function dissolveReconnectModal() {
    const reconnectModal = document.getElementById('reconnectModal');
    if (!reconnectModal) {
        return;
    }

    reconnectModal.classList.add('dissolving');

    setTimeout(function() {
        hideModal('reconnectModal');
        reconnectModal.classList.remove('dissolving');
    }, 300);
}

/**
 * Start editing a pin
 */
function editPin(pinId) {
    // Find the marker with this pin ID
    const marker = existingMarkers.find(m => m.pinData && m.pinData.id == pinId);
    if (!marker) {
        console.error('Pin not found:', pinId);
        return;
    }
    
    const data = marker.pinData;
    
    // Close info window
    infoWindow.close();
    
    // Set edit mode
    editMode = true;
    editingPinId = pinId;
    editingMarker = marker;
    
    // Make the marker draggable by recreating it with a different pin style
    const position = marker.position;
    
    // Create an orange pin for editing
    const editPinElement = new PinElement({
        background: '#ff8f00',
        borderColor: '#e65100',
        glyphColor: 'white',
        scale: 1.2
    });
    
    // Remove existing marker from map
    marker.map = null;
    
    // Create new draggable marker
    draftMarker = new AdvancedMarkerElement({
        position: position,
        map: map,
        gmpDraggable: true,
        content: editPinElement,
        title: 'Drag to adjust position'
    });
    
    // Store pinData on draft marker for reference
    draftMarker.pinData = data;
    
    // Update form coordinates when marker is dragged
    draftMarker.addListener('dragend', function() {
        updateFormCoordinates(draftMarker.position);
    });
    
    // Populate form with existing data
    document.getElementById('name').value = data.name || '';
    document.getElementById('address').value = data.address || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('description').value = data.description || '';
    document.getElementById('latitude').value = data.latitude;
    document.getElementById('longitude').value = data.longitude;
    
    // Handle existing image
    if (data.image_path) {
        const fileUpload = document.getElementById('fileUpload');
        const preview = document.getElementById('filePreview');
        preview.innerHTML = `
            <img src="${escapeHtml(data.image_path)}" alt="Current image">
            <div class="file-preview-name">Current image (upload new to replace)</div>
        `;
        fileUpload.classList.add('has-file');
    }
    
    // Update form title
    document.querySelector('.form-header h2').textContent = 'Edit Your Pin';
    
    // Enable submit button (email already validated)
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtn').textContent = 'Update';
    
    // Make email field readonly during edit
    document.getElementById('email').readOnly = true;
    
    // Show the form panel
    showFormPanel();
    
    // Focus on name field instead of email
    document.getElementById('name').focus();
    
    // Hide instructions
    hideInstructions();
}

/**
 * Show delete confirmation modal
 */
function confirmDeletePin(pinId) {
    window.pendingDeletePinId = pinId;
    infoWindow.close();
    showModal('deletePinModal');
}

/**
 * Delete a pin
 */
async function deletePin(pinId) {
    // Show loading overlay
    document.getElementById('loadingOverlay').classList.add('visible');
    
    const formData = new FormData();
    formData.append('id', pinId);
    formData.append('csrf_token', window.APP_CONFIG.csrfToken);
    
    try {
        const response = await fetch('api/delete.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        // Hide loading overlay
        document.getElementById('loadingOverlay').classList.remove('visible');
        
        if (data.success) {
            // Find and remove the marker from the map
            const markerIndex = existingMarkers.findIndex(m => m.pinData && m.pinData.id == pinId);
            if (markerIndex > -1) {
                existingMarkers[markerIndex].map = null;
                existingMarkers.splice(markerIndex, 1);
            }
            
            // Check if user still has pins
            userHasPins = existingMarkers.some(m => m.isOwner);
            
            alert('Pin deleted successfully.');
        } else {
            alert(data.message || 'An error occurred. Please try again.');
        }
    } catch (error) {
        // Hide loading overlay
        document.getElementById('loadingOverlay').classList.remove('visible');
        
        console.error('Delete error:', error);
        alert('An error occurred. Please check your connection and try again.');
    }
}

/**
 * Initialize UI event handlers
 */
function initUIHandlers() {
    // Form panel close/cancel
    document.getElementById('formClose').addEventListener('click', hideFormPanel);
    document.getElementById('cancelBtn').addEventListener('click', hideFormPanel);

    // Instructions close
    document.getElementById('instructionsClose').addEventListener('click', hideInstructions);

    // About modal
    document.getElementById('aboutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        showModal('aboutModal');
    });
    document.getElementById('aboutClose').addEventListener('click', function() {
        hideModal('aboutModal');
    });
    document.getElementById('aboutBackdrop').addEventListener('click', function() {
        hideModal('aboutModal');
    });

    document.getElementById('reconnectBtn').addEventListener('click', function(e) {
        e.preventDefault();
        showModal('reconnectModal');
        document.getElementById('reconnectEmail').focus();
    });
    document.getElementById('reconnectBackdrop').addEventListener('click', function() {
        hideModal('reconnectModal');
    });

    // Success modal
    document.getElementById('successOk').addEventListener('click', function() {
        hideModal('successModal');
    });
    document.getElementById('successBackdrop').addEventListener('click', function() {
        hideModal('successModal');
    });

    // Add Pin Confirmation modal
    document.getElementById('addPinConfirm').addEventListener('click', function() {
        hideModal('addPinModal');
        if (window.pendingPinPosition) {
            placeNewPin(window.pendingPinPosition);
            window.pendingPinPosition = null;
        }
    });
    document.getElementById('addPinCancel').addEventListener('click', function() {
        hideModal('addPinModal');
        window.pendingPinPosition = null;
    });
    document.getElementById('addPinBackdrop').addEventListener('click', function() {
        hideModal('addPinModal');
        window.pendingPinPosition = null;
    });

    // Delete Pin Confirmation modal
    document.getElementById('deletePinConfirm').addEventListener('click', function() {
        hideModal('deletePinModal');
        if (window.pendingDeletePinId) {
            deletePin(window.pendingDeletePinId);
            window.pendingDeletePinId = null;
        }
    });
    document.getElementById('deletePinCancel').addEventListener('click', function() {
        hideModal('deletePinModal');
        window.pendingDeletePinId = null;
    });
    document.getElementById('deletePinBackdrop').addEventListener('click', function() {
        hideModal('deletePinModal');
        window.pendingDeletePinId = null;
    });

    // Form submission
    document.getElementById('submissionForm').addEventListener('submit', handleFormSubmit);

    const reconnectSubmit = document.getElementById('reconnectSubmit');
    if (reconnectSubmit) {
        reconnectSubmit.addEventListener('click', handleReconnectSubmit);
    }

    // Email validation to enable submit button
    document.getElementById('email').addEventListener('input', validateForm);

    // File upload preview
    document.getElementById('picture').addEventListener('change', handleFileSelect);
    
    // Track if user manually edits the address field
    document.getElementById('address').addEventListener('input', function() {
        addressManuallyEdited = true;
    });
}

/**
 * Show the form panel
 */
function showFormPanel() {
    document.getElementById('formPanel').classList.add('visible');
    document.getElementById('email').focus();
}

/**
 * Hide the form panel and remove draft marker
 */
function hideFormPanel() {
    document.getElementById('formPanel').classList.remove('visible');
    
    // If in edit mode, restore the original marker
    if (editMode && editingMarker) {
        // Remove draft marker
        if (draftMarker) {
            draftMarker.map = null;
            draftMarker = null;
        }
        
        // Restore original marker to map
        editingMarker.map = map;
        
        // Reset edit state
        editMode = false;
        editingMarker = null;
        editingPinId = null;
        
        // Reset form title and button
        document.querySelector('.form-header h2').textContent = 'Add Your Location';
        document.getElementById('submitBtn').textContent = 'Submit';
        document.getElementById('email').readOnly = false;
    } else {
        // Normal mode - remove draft marker
        if (draftMarker) {
            draftMarker.map = null;
            draftMarker = null;
        }
    }
    
    // Reset form
    document.getElementById('submissionForm').reset();
    document.getElementById('submitBtn').disabled = true;
    
    // Reset file upload preview
    const fileUpload = document.getElementById('fileUpload');
    fileUpload.classList.remove('has-file');
    document.getElementById('filePreview').innerHTML = '';
    
    // Reset address tracking flag
    addressManuallyEdited = false;
}

/**
 * Hide instructions banner
 */
function hideInstructions() {
    document.getElementById('instructions').classList.add('hidden');
}

/**
 * Show a modal
 */
function showModal(modalId) {
    document.getElementById(modalId).classList.add('visible');
}

/**
 * Hide a modal
 */
function hideModal(modalId) {
    document.getElementById(modalId).classList.remove('visible');
}

/**
 * Validate form and enable/disable submit button
 */
function validateForm() {
    const email = document.getElementById('email').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isValid = emailRegex.test(email);
    
    document.getElementById('submitBtn').disabled = !isValid;
    
    // Toggle error class
    const emailInput = document.getElementById('email');
    if (email && !isValid) {
        emailInput.classList.add('error');
    } else {
        emailInput.classList.remove('error');
    }
}

/**
 * Handle file selection for preview
 */
function handleFileSelect(event) {
    const file = event.target.files[0];
    const fileUpload = document.getElementById('fileUpload');
    const preview = document.getElementById('filePreview');
    
    if (file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            event.target.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <div class="file-preview-name">${escapeHtml(file.name)}</div>
            `;
            fileUpload.classList.add('has-file');
        };
        reader.readAsDataURL(file);
    } else {
        fileUpload.classList.remove('has-file');
        preview.innerHTML = '';
    }
}

/**
 * Handle form submission
 */
async function handleFormSubmit(event) {
    event.preventDefault();
    
    // Show loading overlay
    document.getElementById('loadingOverlay').classList.add('visible');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Determine API endpoint based on mode
    const endpoint = editMode ? 'api/update.php' : 'api/submit.php';
    
    // Add pin ID if editing
    if (editMode && editingPinId) {
        formData.append('id', editingPinId);
    }
    
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        // Hide loading overlay
        document.getElementById('loadingOverlay').classList.remove('visible');
        
        if (data.success) {
            const submittedEmail = formData.get('email');
            if (submittedEmail) {
                saveUserEmail(submittedEmail);
            }

            if (editMode) {
                // Update was successful
                // Update the marker data and recreate with new position
                const updatedPin = data.pin;
                
                // Remove the draft marker
                if (draftMarker) {
                    draftMarker.map = null;
                    draftMarker = null;
                }
                
                // Remove old marker from array
                const markerIndex = existingMarkers.indexOf(editingMarker);
                if (markerIndex > -1) {
                    existingMarkers.splice(markerIndex, 1);
                }
                
                // Add updated marker
                addExistingMarker(updatedPin);
                
                // Reset edit state
                editMode = false;
                editingMarker = null;
                editingPinId = null;
                
                // Reset form UI
                document.querySelector('.form-header h2').textContent = 'Add Your Location';
                document.getElementById('submitBtn').textContent = 'Submit';
                document.getElementById('email').readOnly = false;
                
                // Show success message
                alert('Pin updated successfully!');
                
                // Hide form and reset
                hideFormPanel();
                
                // Pan to updated location
                map.panTo({ 
                    lat: parseFloat(updatedPin.latitude), 
                    lng: parseFloat(updatedPin.longitude) 
                });
            } else {
                // New submission - show success modal
                document.getElementById('successEmail').textContent = formData.get('email');
                showModal('successModal');
                
                // Hide form panel and reset
                hideFormPanel();
            }
        } else {
            alert(data.message || 'An error occurred. Please try again.');
        }
    } catch (error) {
        // Hide loading overlay
        document.getElementById('loadingOverlay').classList.remove('visible');
        
        console.error('Submission error:', error);
        alert('An error occurred. Please check your connection and try again.');
    }
}

async function handleReconnectSubmit(event) {
    event.preventDefault();

    const emailInput = document.getElementById('reconnectEmail');
    const email = (emailInput.value || '').trim();
    const statusElement = document.getElementById('reconnectStatus');
    const formData = new FormData();
    formData.append('email', email);
    formData.append('csrf_token', window.APP_CONFIG.csrfToken);

    if (!email) {
        statusElement.textContent = 'Please enter your email address.';
        statusElement.className = 'reconnect-status error';
        return;
    }

    statusElement.textContent = 'Checking for your sites...';
    statusElement.className = 'reconnect-status';

    try {
        const response = await fetch('api/reconnect.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            saveUserEmail(email);
            statusElement.textContent = data.message || 'Reconnected. Your pins are now editable on this device.';
            statusElement.className = 'reconnect-status success';
            loadExistingPins();
            dissolveReconnectModal();
        } else {
            statusElement.textContent = data.message || 'No confirmed sites were found for that email.';
            statusElement.className = 'reconnect-status error';
        }
    } catch (error) {
        console.error('Reconnect error:', error);
        statusElement.textContent = 'Could not reconnect right now. Please try again.';
        statusElement.className = 'reconnect-status error';
    }
}

/**
 * Add a newly confirmed pin to the map (called after confirmation redirect)
 */
function addNewPin(pinData) {
    addExistingMarker(pinData);
    
    // Pan to the new pin
    map.panTo({ 
        lat: parseFloat(pinData.latitude), 
        lng: parseFloat(pinData.longitude) 
    });
    map.setZoom(15);
}

/**
 * Check for URL parameters (e.g., after confirmation)
 */
function checkUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const confirmed = urlParams.get('confirmed');
    const lat = urlParams.get('lat');
    const lng = urlParams.get('lng');
    
    if (confirmed === '1' && lat && lng) {
        // Pan to the confirmed location
        setTimeout(function() {
            map.panTo({ lat: parseFloat(lat), lng: parseFloat(lng) });
            map.setZoom(15);
        }, 500);
        
        // Clear URL parameters
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Initialize map when DOM is ready
initMap();
