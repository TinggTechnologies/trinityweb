// Create Release Page Functionality

// Get the correct base path for uploads
const CREATE_RELEASE_UPLOADS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.uploadsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/uploads'
        : '/uploads';

// Genres list
const genres = [
    "Abstract Art", "Action (film/lit)", "Afrobeat", "Afrofusion", "Afropop", "Alternative Rock", "Ambient", "Americana",
    "Animation", "Anime", "Arabic Pop", "Art Rock", "Avant-garde", "Bachata", "Ballet", "Baroque", "Bhangra", "Biopic",
    "Blues", "Bluegrass", "Bolero", "Bollywood", "Bossanova", "Calypso", "Cambodian Rock", "Cape Jazz", "Carnatic",
    "Chanson", "Chillout", "Choral", "Christian / Gospel", "Classical", "Comedy", "Comic / Graphic Novel", "Country",
    "Cumbia", "Cyberpunk", "Dancehall", "Dark Wave", "Deep House", "Disco", "Documentary", "Drama",
    "Drill (UK, US, African)", "Dub", "Dubstep", "EDM (Electronic Dance Music)", "Electro Pop", "Electronic",
    "Epic (film/lit)", "Ethno-jazz", "Experimental", "Fantasy", "Fiction", "Film Noir", "Flamenco",
    "Folk (various regions)", "Folk Rock", "Funk", "Fusion", "Garage", "Ghazal", "Gospel", "Grime", "Grunge",
    "Hard Rock", "Heavy Metal", "Hip-Hop", "Highlife", "Historical Fiction", "Horror", "House", "Impressionism",
    "Indie Pop", "Indie Rock", "Industrial", "Instrumental", "Jazz", "Jazz Fusion", "Jit (Zimbabwean genre)",
    "K-pop", "Kizomba", "Kompa", "Kwela", "Latin Jazz", "Latin Pop", "Latin Rock", "Lo-fi", "Makossa", "Maloya",
    "Mandopop", "Mbalax", "Metalcore", "Minimalism (art/music)", "Morna", "Motown", "Mugham", "Musical Theatre",
    "Neo-Soul", "New Age", "New Wave", "Ngoma", "Non-Fiction", "Novel", "Nu-Disco", "Opera", "Orchestral",
    "Pachanga", "Pagan Metal", "Piano Blues", "Poetry", "Pop", "Pop Rock", "Post-punk", "Post-rock", "Punk",
    "Qawwali", "R&B (Rhythm and Blues)", "Ragga", "Ragtime", "Rap", "Reggae", "Reggaeton", "Religious / Spiritual",
    "Rhumba", "Rock", "Romance (literature/film)", "Romanticism (art/lit/music)", "Salsa", "Samba", "Sci-Fi",
    "Sega", "Ska", "Smooth Jazz", "Soca", "Soul", "Soundtrack", "Spoken Word", "Surf Rock", "Swing", "Synthpop",
    "Tango", "Techno", "Thriller", "Trance", "Trap", "Trip-Hop", "Tropical House", "Vallenato", "Western",
    "World Music", "Zouk"
];

// Global variable to track edit mode
let editMode = false;
let editReleaseId = null;

// Global variable to store artists
let artistsList = [];
let currentArtistSelectTarget = null;
let addArtistModal = null;

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('currentYear').textContent = new Date().getFullYear();

    // Load artists from database
    await loadArtists();

    // Check if we're in edit mode
    const urlParams = new URLSearchParams(window.location.search);
    editReleaseId = urlParams.get('edit');
    editMode = !!editReleaseId;

    // Update page title if in edit mode
    if (editMode) {
        const pageTitle = document.querySelector('.new-heading');
        if (pageTitle) {
            pageTitle.textContent = 'Edit Release';
        }
    }

    // Populate genres
    const genreSelect = document.getElementById('genre');
    genres.forEach(genre => {
        const option = document.createElement('option');
        option.value = genre;
        option.textContent = genre;
        genreSelect.appendChild(option);
    });

    // Populate subgenres (same as genres)
    const subgenreSelect = document.getElementById('subgenre');
    genres.forEach(genre => {
        const option = document.createElement('option');
        option.value = genre;
        option.textContent = genre;
        subgenreSelect.appendChild(option);
    });

    // Populate years (2030 down to 1800)
    const cYearSelect = document.getElementById('c_line_year');
    const pYearSelect = document.getElementById('p_line_year');
    for (let year = 2030; year >= 1800; year--) {
        const cOption = document.createElement('option');
        cOption.value = year;
        cOption.textContent = year;
        cYearSelect.appendChild(cOption);

        const pOption = document.createElement('option');
        pOption.value = year;
        pOption.textContent = year;
        pYearSelect.appendChild(pOption);
    }

    // Load release data if in edit mode, otherwise load next catalog
    if (editMode) {
        await loadReleaseData(editReleaseId);
    } else {
        await loadNextCatalog();
    }

    // Setup event listeners
    setupEventListeners();
});

async function loadNextCatalog() {
    try {
        const response = await API.get('/releases/next-catalog');
        if (response.success && response.data) {
            document.getElementById('catalog_number').value = response.data.catalog_number;
        }
    } catch (error) {
        console.error('Failed to load catalog number:', error);
    }
}

async function loadReleaseData(releaseId) {
    try {
        const response = await API.get(`/releases/${releaseId}`);
        if (response.success && response.data) {
            const release = response.data;

            console.log('Loading release data:', release);

            // Populate form fields (editable by user)
            document.getElementById('release_title').value = release.release_title || '';
            document.getElementById('release_version').value = release.release_version || '';
            document.getElementById('genre').value = release.genre || '';
            document.getElementById('subgenre').value = release.subgenre || '';
            document.getElementById('label_name').value = release.label_name || '';
            document.getElementById('c_line_year').value = release.c_line_year || '';
            document.getElementById('c_line_text').value = release.c_line_text || '';
            document.getElementById('p_line_year').value = release.p_line_year || '';
            document.getElementById('p_line_text').value = release.p_line_text || '';

            // System-generated/Admin-controlled fields (readonly)
            const catalogField = document.getElementById('catalog_number');
            if (catalogField) {
                catalogField.value = release.catalog_number || '';
                console.log('Catalog number set to:', release.catalog_number);
            }

            // Handle UPC field
            const upcInput = document.getElementById('upc');
            const upcManual = document.getElementById('upc_manual');
            const upcAuto = document.getElementById('upc_auto');
            const upcRadioContainer = document.querySelector('.upc-options');

            if (release.upc && upcInput) {
                upcInput.value = release.upc;
                upcInput.style.display = 'block';

                // Check if UPC was set by admin - make read-only with badge
                if (release.upc_set_by_admin == 1) {
                    upcInput.readOnly = true;
                    upcInput.style.backgroundColor = '#e9ecef';
                    upcInput.style.cursor = 'not-allowed';

                    // Hide radio options and show "Generated by Admin" badge
                    if (upcRadioContainer) {
                        upcRadioContainer.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Generated by Admin</span>';
                    }
                } else {
                    // UPC set by user - keep editable, select manual option
                    if (upcManual) upcManual.checked = true;
                    if (upcAuto) upcAuto.checked = false;
                }
            }

            // Handle ISRC field
            const isrcInput = document.getElementById('isrc');
            const isrcManual = document.getElementById('isrc_manual');
            const isrcAuto = document.getElementById('isrc_auto');
            const isrcRadioContainer = document.querySelector('.isrc-options');

            if (release.isrc && isrcInput) {
                isrcInput.value = release.isrc;
                isrcInput.style.display = 'block';

                // Check if ISRC was set by admin - make read-only with badge
                if (release.isrc_set_by_admin == 1) {
                    isrcInput.readOnly = true;
                    isrcInput.style.backgroundColor = '#e9ecef';
                    isrcInput.style.cursor = 'not-allowed';

                    // Hide radio options and show "Generated by Admin" badge
                    if (isrcRadioContainer) {
                        isrcRadioContainer.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Generated by Admin</span>';
                    }
                } else {
                    // ISRC set by user - keep editable, select manual option
                    if (isrcManual) isrcManual.checked = true;
                    if (isrcAuto) isrcAuto.checked = false;
                }
            }

            // Handle Pricing Tier field
            if (release.pricing_tier) {
                document.getElementById('pricing_tier').value = release.pricing_tier;
            }

            // Load artwork if exists
            if (release.artwork_path) {
                const artworkPreview = document.getElementById('artworkPreview');
                // Fix artwork path
                const cleanPath = release.artwork_path.replace(/^uploads\//, '');
                const artworkUrl = `${CREATE_RELEASE_UPLOADS_PATH}/${cleanPath}`;
                artworkPreview.innerHTML = `<img src="${artworkUrl}" alt="Artwork" style="width: 100%; height: 100%; object-fit: cover; border-radius: 5px;">`;

                // Make artwork field optional when editing (since artwork already exists)
                const artworkInput = document.getElementById('artwork');
                if (artworkInput) {
                    artworkInput.removeAttribute('required');
                }
            }

            // Load artists if exists
            if (release.artist_list && Array.isArray(release.artist_list) && release.artist_list.length > 0) {
                const container = document.getElementById('stageNamesContainer');
                container.innerHTML = ''; // Clear existing

                release.artist_list.forEach((artist, index) => {
                    const artistName = artist.stage_name || '';

                    if (index === 0) {
                        // First artist uses the default input (no remove button)
                        const firstInput = document.createElement('div');
                        firstInput.className = 'artist-input-group';
                        firstInput.innerHTML = `
                            <div class="input-group">
                                <select class="form-control artist-select" name="stage_names[]" required>
                                    <option value="">Select or type to add new artist</option>
                                    ${artistsList.map(a => `<option value="${escapeHtml(a.name)}" ${a.name === artistName ? 'selected' : ''}>${escapeHtml(a.name)}</option>`).join('')}
                                </select>
                                <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        `;
                        container.appendChild(firstInput);

                        // Add "add new artist" button handler
                        firstInput.querySelector('.add-new-artist-btn').addEventListener('click', (e) => {
                            currentArtistSelectTarget = e.target.closest('.input-group').querySelector('.artist-select');
                            if (addArtistModal) {
                                addArtistModal.show();
                            }
                        });

                        // If artist not in list, add it
                        if (artistName && !artistsList.find(a => a.name === artistName)) {
                            const select = firstInput.querySelector('.artist-select');
                            const opt = document.createElement('option');
                            opt.value = artistName;
                            opt.textContent = artistName;
                            opt.selected = true;
                            select.appendChild(opt);
                        }
                    } else {
                        // Additional artists (with remove button)
                        const newElement = createArtistSelectElement(artistName);
                        container.appendChild(newElement);

                        // Add remove handler
                        newElement.querySelector('.remove-artist').addEventListener('click', () => {
                            newElement.remove();
                        });

                        // Add "add new artist" button handler
                        newElement.querySelector('.add-new-artist-btn').addEventListener('click', (e) => {
                            currentArtistSelectTarget = e.target.closest('.input-group').querySelector('.artist-select');
                            if (addArtistModal) {
                                addArtistModal.show();
                            }
                        });

                        // If artist not in list, add it to select
                        if (artistName && !artistsList.find(a => a.name === artistName)) {
                            const select = newElement.querySelector('.artist-select');
                            const opt = document.createElement('option');
                            opt.value = artistName;
                            opt.textContent = artistName;
                            opt.selected = true;
                            select.appendChild(opt);
                        }
                    }
                });
            }

            console.log('Release data loaded successfully');
        } else {
            showError('Failed to load release data');
        }
    } catch (error) {
        console.error('Error loading release data:', error);
        showError('Failed to load release data');
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Load artists from database
 */
async function loadArtists() {
    try {
        const response = await API.get('artists');
        if (response.success) {
            artistsList = response.data.artists || [];
            populateArtistSelects();
        }
    } catch (error) {
        console.error('Error loading artists:', error);
    }
}

/**
 * Populate all artist select dropdowns (preserves existing selections)
 */
function populateArtistSelects() {
    const selects = document.querySelectorAll('.artist-select');
    selects.forEach(select => {
        const currentValue = select.value;

        // Keep the first option
        select.innerHTML = '<option value="">Select or type to add new artist</option>';

        // Add artists from the list
        artistsList.forEach(artist => {
            const option = document.createElement('option');
            option.value = artist.name;
            option.textContent = artist.name;
            // Mark as selected if this was the previous value
            if (artist.name === currentValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        // If the current value exists but is not in artistsList, add it as an option
        if (currentValue && !artistsList.find(a => a.name === currentValue)) {
            const option = document.createElement('option');
            option.value = currentValue;
            option.textContent = currentValue;
            option.selected = true;
            select.appendChild(option);
        }
    });
}

/**
 * Create a new artist select element
 */
function createArtistSelectElement(selectedValue = '') {
    const div = document.createElement('div');
    div.className = 'artist-input-group mt-2';
    div.innerHTML = `
        <div class="input-group">
            <select class="form-control artist-select" name="stage_names[]" required>
                <option value="">Select or type to add new artist</option>
                ${artistsList.map(a => `<option value="${escapeHtml(a.name)}" ${a.name === selectedValue ? 'selected' : ''}>${escapeHtml(a.name)}</option>`).join('')}
            </select>
            <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button type="button" class="btn btn-danger remove-artist" title="Remove">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    return div;
}

/**
 * Save new artist to database
 */
async function saveNewArtist(name) {
    try {
        const response = await API.post('artists', { name: name });
        if (response.success) {
            const newArtist = response.data.artist;

            // Add to local list if not already there
            if (!artistsList.find(a => a.name === newArtist.name)) {
                artistsList.push(newArtist);
            }

            // Add the new artist option to all selects (without clearing them)
            addArtistToAllSelects(newArtist.name);

            return newArtist;
        } else {
            throw new Error(response.message || 'Failed to save artist');
        }
    } catch (error) {
        console.error('Error saving artist:', error);
        throw error;
    }
}

/**
 * Add a new artist to all select dropdowns without clearing existing selections
 */
function addArtistToAllSelects(artistName) {
    const selects = document.querySelectorAll('.artist-select');
    selects.forEach(select => {
        // Check if this artist already exists in the select
        const existingOption = Array.from(select.options).find(opt => opt.value === artistName);
        if (!existingOption) {
            const option = document.createElement('option');
            option.value = artistName;
            option.textContent = artistName;
            select.appendChild(option);
        }
    });
}

function setupEventListeners() {
    // Artwork preview
    const artworkInput = document.getElementById('artwork');
    const artworkPreview = document.getElementById('artworkPreview');
    const removeArtworkBtn = document.getElementById('removeArtwork');
    
    artworkInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                artworkPreview.innerHTML = `<img src="${e.target.result}" alt="Artwork Preview">`;
            };
            reader.readAsDataURL(file);
        }
    });
    
    removeArtworkBtn.addEventListener('click', () => {
        artworkInput.value = '';
        artworkPreview.innerHTML = '<i class="fas fa-fw fa-image"></i>';
    });
    
    // Add artist button - adds another artist select
    document.getElementById('addArtist').addEventListener('click', () => {
        const container = document.getElementById('stageNamesContainer');
        const newElement = createArtistSelectElement();
        container.appendChild(newElement);

        // Add remove handler
        newElement.querySelector('.remove-artist').addEventListener('click', () => {
            newElement.remove();
        });

        // Add "add new artist" button handler
        newElement.querySelector('.add-new-artist-btn').addEventListener('click', (e) => {
            currentArtistSelectTarget = e.target.closest('.input-group').querySelector('.artist-select');
            if (addArtistModal) {
                addArtistModal.show();
            }
        });
    });

    // Initialize the modal once
    const addArtistModalEl = document.getElementById('addArtistModal');
    if (addArtistModalEl) {
        addArtistModal = new bootstrap.Modal(addArtistModalEl);
    }

    // Setup "add new artist" buttons for existing selects
    document.querySelectorAll('.add-new-artist-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            currentArtistSelectTarget = e.target.closest('.input-group').querySelector('.artist-select');
            if (addArtistModal) {
                addArtistModal.show();
            }
        });
    });

    // Save new artist button in modal
    document.getElementById('saveNewArtistBtn').addEventListener('click', async () => {
        const nameInput = document.getElementById('newArtistName');
        const name = nameInput.value.trim();

        if (!name) {
            nameInput.classList.add('is-invalid');
            return;
        }

        nameInput.classList.remove('is-invalid');

        try {
            const artist = await saveNewArtist(name);

            // Close modal
            if (addArtistModal) {
                addArtistModal.hide();
            }

            // Clear input
            nameInput.value = '';

            // Select the new artist in the target select
            if (currentArtistSelectTarget) {
                currentArtistSelectTarget.value = artist.name;
            }

        } catch (error) {
            alert('Failed to save artist: ' + error.message);
        }
    });

    // Clear modal input when closed
    if (addArtistModalEl) {
        addArtistModalEl.addEventListener('hidden.bs.modal', () => {
            document.getElementById('newArtistName').value = '';
            document.getElementById('newArtistName').classList.remove('is-invalid');
            // Remove any lingering backdrop
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        });
    }
    
    // UPC option toggle (radio buttons)
    const upcRadios = document.querySelectorAll('input[name="upc_option"]');
    const upcInput = document.getElementById('upc');

    if (upcInput && upcRadios.length > 0) {
        upcRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'manual') {
                    upcInput.style.display = 'block';
                    upcInput.focus();
                } else {
                    upcInput.style.display = 'none';
                    upcInput.value = '';
                }
            });
        });
    }

    // ISRC option toggle (radio buttons)
    const isrcRadios = document.querySelectorAll('input[name="isrc_option"]');
    const isrcInput = document.getElementById('isrc');

    if (isrcInput && isrcRadios.length > 0) {
        isrcRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'manual') {
                    isrcInput.style.display = 'block';
                    isrcInput.focus();
                } else {
                    isrcInput.style.display = 'none';
                    isrcInput.value = '';
                }
            });
        });
    }
    
    // Form submission
    document.getElementById('releaseForm').addEventListener('submit', handleSubmit);
}

async function handleSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    // Hide previous messages
    document.getElementById('errorMessages').classList.add('d-none');
    document.getElementById('successMessage').classList.add('d-none');

    // Validate artwork - required for new releases, optional for edits
    const artworkInput = document.getElementById('artwork');
    const artworkPreview = document.getElementById('artworkPreview');
    const hasExistingArtwork = artworkPreview && artworkPreview.querySelector('img') !== null;

    if (!editMode && !artworkInput.files.length) {
        const errorDiv = document.getElementById('errorMessages');
        errorDiv.innerHTML = '<strong>Error:</strong> Please upload a cover art image.';
        errorDiv.classList.remove('d-none');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // For edit mode, only require artwork if no existing artwork
    if (editMode && !artworkInput.files.length && !hasExistingArtwork) {
        const errorDiv = document.getElementById('errorMessages');
        errorDiv.innerHTML = '<strong>Error:</strong> Please upload a cover art image.';
        errorDiv.classList.remove('d-none');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // Show loader
    document.getElementById('loaderOverlay').classList.add('active');

    try {
        let response;

        if (editMode && editReleaseId) {
            // Update existing release
            response = await API.put(`/releases/${editReleaseId}`, formData, true); // true for multipart
        } else {
            // Create new release
            response = await API.post('/releases', formData, true); // true for multipart
        }

        if (response.success) {
            // Redirect to release continuation page to add tracks
            const releaseId = editMode ? editReleaseId : response.data.release_id;
            window.location.href = `release-cont?release_id=${releaseId}`;
        } else {
            throw new Error(response.message || `Failed to ${editMode ? 'update' : 'create'} release`);
        }
    } catch (error) {
        console.error(`Error ${editMode ? 'updating' : 'creating'} release:`, error);
        const errorDiv = document.getElementById('errorMessages');

        // Display detailed error message
        let errorMessage = `Failed to ${editMode ? 'update' : 'create'} release. Please try again.`;
        if (error.message) {
            errorMessage = error.message;
        }

        errorDiv.innerHTML = `<strong>Error:</strong> ${errorMessage}`;
        errorDiv.classList.remove('d-none');

        // Scroll to error message
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } finally {
        document.getElementById('loaderOverlay').classList.remove('active');
    }
}

