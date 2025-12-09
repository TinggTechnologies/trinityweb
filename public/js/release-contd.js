/**
 * Release Continuation - Track Management
 * Matches the exact structure from release-cont.html
 */

console.log('release-contd.js loaded');

let releaseId = null;
let releaseData = null;
let wavesurfer = null;
let currentAudioFile = null;
let existingTrackId = null; // Track ID if editing existing track
let uploadedAudioPath = null; // Path to uploaded audio file
let isAudioUploading = false; // Flag to track upload status

// Artist selection variables
let artistsList = [];
let currentArtistSelectTarget = null;
let addArtistModal = null;

// Constants
const MAX_AUDIO_SIZE_MB = 300;
const MAX_AUDIO_SIZE_BYTES = MAX_AUDIO_SIZE_MB * 1024 * 1024;

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    console.log('DOMContentLoaded - release-contd.js initializing...');

    // Get release ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    releaseId = urlParams.get('release_id');

    console.log('Release ID:', releaseId);

    if (!releaseId) {
        alert('No release ID provided');
        window.location.href = './create-release';
        return;
    }

    // Load artists from database
    await loadArtists();

    // Load release details
    loadReleaseDetails();

    // Setup event listeners
    setupEventListeners();

    // Setup drag and drop
    setupDragAndDrop();

    // Setup split share functionality
    setupSplitShare();

    // Setup artist modal
    setupArtistModal();

    // Check if URL has hash to navigate to split share tab
    if (window.location.hash === '#split-share') {
        const splitShareTab = document.getElementById('split-share-tab');
        if (splitShareTab) {
            const tab = new bootstrap.Tab(splitShareTab);
            tab.show();
        }
        // Load invitations immediately when navigating to split share tab
        loadSplitShareInvitations();
    }

    console.log('Initialization complete');
});

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
        select.innerHTML = '<option value="">Select artist</option>';

        // Add artists from the list
        artistsList.forEach(artist => {
            const option = document.createElement('option');
            option.value = artist.name;
            option.textContent = artist.name;
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
 * Add a new artist to all select dropdowns without clearing existing selections
 */
function addArtistToAllSelects(artistName) {
    const selects = document.querySelectorAll('.artist-select');
    selects.forEach(select => {
        const existingOption = Array.from(select.options).find(opt => opt.value === artistName);
        if (!existingOption) {
            const option = document.createElement('option');
            option.value = artistName;
            option.textContent = artistName;
            select.appendChild(option);
        }
    });
}

/**
 * Save new artist to database
 */
async function saveNewArtist(name) {
    try {
        const response = await API.post('artists', { name: name });
        if (response.success) {
            const newArtist = response.data.artist;

            if (!artistsList.find(a => a.name === newArtist.name)) {
                artistsList.push(newArtist);
            }

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
 * Setup artist modal functionality
 */
function setupArtistModal() {
    const addArtistModalEl = document.getElementById('addArtistModal');
    if (addArtistModalEl) {
        addArtistModal = new bootstrap.Modal(addArtistModalEl);

        // Setup add new artist buttons using event delegation
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.add-new-artist-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const inputGroup = btn.closest('.input-group');
                if (inputGroup) {
                    currentArtistSelectTarget = inputGroup.querySelector('.artist-select');
                }
                if (addArtistModal) {
                    addArtistModal.show();
                }
            }
        });

        // Save new artist button
        const saveBtn = document.getElementById('saveNewArtistBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const nameInput = document.getElementById('newArtistName');
                const name = nameInput.value.trim();

                if (!name) {
                    nameInput.classList.add('is-invalid');
                    return;
                }

                nameInput.classList.remove('is-invalid');

                try {
                    const artist = await saveNewArtist(name);

                    if (addArtistModal) {
                        addArtistModal.hide();
                    }

                    nameInput.value = '';

                    if (currentArtistSelectTarget) {
                        currentArtistSelectTarget.value = artist.name;
                    }

                } catch (error) {
                    alert('Failed to save artist: ' + error.message);
                }
            });
        }

        // Clear modal on close
        addArtistModalEl.addEventListener('hidden.bs.modal', () => {
            const nameInput = document.getElementById('newArtistName');
            if (nameInput) {
                nameInput.value = '';
                nameInput.classList.remove('is-invalid');
            }
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        });
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    console.log('Setting up event listeners...');

    // Set current year in footer
    const currentYearElement = document.getElementById('currentYear');
    if (currentYearElement) {
        currentYearElement.textContent = new Date().getFullYear();
    }

    // Save button
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveTrack);
    }

    // Back button
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            window.location.href = './create-release';
        });
    }

    // Select All Partners checkbox
    const selectAllPartnersCheckbox = document.getElementById('selectAllPartners');
    if (selectAllPartnersCheckbox) {
        selectAllPartnersCheckbox.addEventListener('change', function() {
            const partnerCheckboxes = document.querySelectorAll('.partner-box input[type="checkbox"]');
            partnerCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Also update "Select All" when individual partner checkboxes change
        const partnerCheckboxes = document.querySelectorAll('.partner-box input[type="checkbox"]');
        partnerCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.partner-box input[type="checkbox"]');
                const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
                const someChecked = Array.from(allCheckboxes).some(cb => cb.checked);

                selectAllPartnersCheckbox.checked = allChecked;
                selectAllPartnersCheckbox.indeterminate = someChecked && !allChecked;
            });
        });
    }

    // Add Display Artist button
    const addDisplayArtistBtn = document.getElementById('addDisplayArtistBtn');
    if (addDisplayArtistBtn) {
        console.log('Add Display Artist button found');
        addDisplayArtistBtn.addEventListener('click', addDisplayArtist);
    } else {
        console.log('Add Display Artist button NOT found');
    }

    // Add Writer button
    const addWriterBtn = document.getElementById('addWriterBtn');
    if (addWriterBtn) {
        console.log('Add Writer button found');
        addWriterBtn.addEventListener('click', addWriter);
    } else {
        console.log('Add Writer button NOT found');
    }

    // Add Production & Engineering button
    const addProductionBtn = document.getElementById('addProductionBtn');
    if (addProductionBtn) {
        console.log('Add Production button found');
        addProductionBtn.addEventListener('click', addProduction);
    } else {
        console.log('Add Production button NOT found');
    }

    // Add Performer button
    const addPerformerBtn = document.getElementById('addPerformerBtn');
    if (addPerformerBtn) {
        console.log('Add Performer button found');
        addPerformerBtn.addEventListener('click', addPerformer);
    } else {
        console.log('Add Performer button NOT found');
    }

    // Setup remove buttons for existing artists/contributors
    setupRemoveButtons();

    // Worldwide release toggle
    const worldwideReleaseToggle = document.getElementById('worldwideRelease');
    if (worldwideReleaseToggle) {
        worldwideReleaseToggle.addEventListener('change', function() {
            const label = document.getElementById('worldwideReleaseLabel');
            if (label) {
                label.textContent = this.checked ? 'Yes' : 'No';
            }
        });
    }

    console.log('Event listeners setup complete');
}

/**
 * Load release details
 */
async function loadReleaseDetails() {
    try {
        const response = await API.get(`releases/${releaseId}`);
        console.log('=== RELEASE DETAILS RESPONSE ===');
        console.log('Full response:', JSON.stringify(response, null, 2));

        if (response.success) {
            releaseData = response.data;
            console.log('Release data:', releaseData);
            console.log('Tracks:', releaseData.tracks);
            console.log('Stores:', releaseData.stores);

            displayReleaseInfo();

            // Load partner stores
            if (releaseData.stores && releaseData.stores.length > 0) {
                console.log('Loading partner stores...');
                loadPartnerStores(releaseData.stores);
            } else {
                console.warn('No stores found in release data');
            }

            // Load existing track data if available
            if (releaseData.tracks && releaseData.tracks.length > 0) {
                console.log('Loading track data for track ID:', releaseData.tracks[0].id);
                existingTrackId = releaseData.tracks[0].id; // Store track ID for updates
                loadTrackData(releaseData.tracks[0]); // Load first track
            } else {
                console.warn('No tracks found in release data');
            }
        } else {
            showError(response.message || 'Failed to load release details');
        }
    } catch (error) {
        console.error('Error loading release:', error);
        showError('Failed to load release details');
    }
}

/**
 * Display release information
 */
function displayReleaseInfo() {
    const titleElement = document.getElementById('releaseTitle');
    if (titleElement) {
        titleElement.textContent = releaseData.release_title || 'Track Details';
    }
}

/**
 * Load track data into form
 */
function loadTrackData(track) {
    console.log('=== LOADING TRACK DATA ===');
    console.log('Full track object:', JSON.stringify(track, null, 2));

    // Populate track details
    const songNameInput = document.getElementById('songName');
    if (songNameInput) {
        songNameInput.value = track.track_title || '';
        console.log('Song name set to:', track.track_title);
    } else {
        console.error('Song name input not found');
    }

    const versionInput = document.getElementById('trackVersion');
    if (versionInput) {
        versionInput.value = track.track_version || '';
        console.log('Track version set to:', track.track_version);
    }

    // ISRC is now at release level, not track level

    const previewStartInput = document.getElementById('previewStart');
    if (previewStartInput) {
        previewStartInput.value = track.preview_start || 0;
        console.log('Preview start set to:', track.preview_start);
    }

    // Set explicit status radio button
    if (track.explicit_content) {
        console.log('Setting explicit status to:', track.explicit_content);
        const explicitRadio = document.querySelector(`input[name="explicit-status"][value="${track.explicit_content}"]`);
        if (explicitRadio) {
            explicitRadio.checked = true;
            console.log('Explicit radio button checked');
        } else {
            console.log('Explicit radio button not found for value:', track.explicit_content);
        }
    }

    // Set audio style radio button
    if (track.audio_style) {
        console.log('Setting audio style to:', track.audio_style);
        const audioStyleRadio = document.querySelector(`input[name="audio-style"][value="${track.audio_style}"]`);
        if (audioStyleRadio) {
            audioStyleRadio.checked = true;
            console.log('Audio style radio button checked');
        } else {
            console.log('Audio style radio button not found for value:', track.audio_style);
        }
    }

    // Set language
    const languageSelect = document.getElementById('language');
    if (languageSelect && track.language) languageSelect.value = track.language;

    // Set recording year
    const recordingYearSelect = document.getElementById('recordingYear');
    console.log('Recording year from track:', track.recording_year);
    console.log('Recording year select element:', recordingYearSelect);
    if (recordingYearSelect) {
        if (track.recording_year) {
            recordingYearSelect.value = track.recording_year;
            console.log('Recording year set to:', track.recording_year);
            console.log('Recording year select value after setting:', recordingYearSelect.value);
        } else {
            console.warn('No recording year in track data');
        }
    } else {
        console.error('Recording year select element not found');
    }

    // Set recording country
    const recordingCountrySelect = document.getElementById('recordingCountry');
    console.log('Recording country from track:', track.recording_country);
    console.log('Recording country select element:', recordingCountrySelect);
    if (recordingCountrySelect) {
        if (track.recording_country) {
            recordingCountrySelect.value = track.recording_country;
            console.log('Recording country set to:', track.recording_country);
            console.log('Recording country select value after setting:', recordingCountrySelect.value);
        } else {
            console.warn('No recording country in track data');
        }
    } else {
        console.error('Recording country select element not found');
    }

    // Set release date
    const releaseDateInput = document.getElementById('releaseDate');
    if (releaseDateInput && track.release_date) {
        releaseDateInput.value = track.release_date;
        console.log('Release date set to:', track.release_date);
    }

    // Set release time
    const releaseTimeInput = document.getElementById('releaseTime');
    if (releaseTimeInput && track.release_time) {
        releaseTimeInput.value = track.release_time;
        console.log('Release time set to:', track.release_time);
    }

    // Set worldwide release toggle
    const worldwideReleaseToggle = document.getElementById('worldwideRelease');
    const worldwideReleaseLabel = document.getElementById('worldwideReleaseLabel');
    if (worldwideReleaseToggle) {
        worldwideReleaseToggle.checked = track.worldwide_release == 1;
        if (worldwideReleaseLabel) {
            worldwideReleaseLabel.textContent = track.worldwide_release == 1 ? 'Yes' : 'No';
        }
        console.log('Worldwide release set to:', track.worldwide_release);
    }

    // Load display artists
    if (track.artists && Array.isArray(track.artists)) {
        console.log('All track artists:', track.artists);

        const displayArtists = track.artists.filter(a => a.type === 'display');
        console.log('Display artists found:', displayArtists);

        const displayContainer = document.getElementById('displayArtistsContainer');
        if (displayContainer && displayArtists.length > 0) {
            displayContainer.innerHTML = ''; // Clear existing

            displayArtists.forEach((artist, index) => {
                const artistRow = createArtistRow(artist.artist_name, artist.role);
                displayContainer.appendChild(artistRow);
            });
        }

        // Load contributors (writers, production, performers)
        // Filter by role patterns since we don't have a category field in the database
        const writerRoles = ['arranger', 'composer', 'lyricist', 'songwriter', 'transcriber', 'vocal-adaptation'];
        const productionRoles = [
            'assistant-engineer',
            'co-producer',
            'graphic-design',
            'guitar-technician',
            'mastering-engineer',
            'mixing-engineer',
            'producer',
            'production-assistant',
            'recording-engineer',
            'sound-engineer',
            'vocal-engineer'
        ];
        const performerRoles = [
            'background-vocalist',
            'bass',
            'cello',
            'drums',
            'guitar',
            'keyboard',
            'percussion',
            'piano',
            'saxophone',
            'trumpet',
            'violin',
            'vocals'
        ];

        const writers = track.artists.filter(a => a.type === 'contributor' && writerRoles.includes(a.role));
        console.log('Writers found:', writers);

        const writersContainer = document.getElementById('writersContainer');
        if (writersContainer && writers.length > 0) {
            writersContainer.innerHTML = '';
            writers.forEach(writer => {
                const writerRow = createContributorRow(writer.artist_name, writer.role, 'writer');
                writersContainer.appendChild(writerRow);
            });
        }

        const production = track.artists.filter(a => a.type === 'contributor' && productionRoles.includes(a.role));
        console.log('Production found:', production);

        const productionContainer = document.getElementById('productionContainer');
        if (productionContainer && production.length > 0) {
            productionContainer.innerHTML = '';
            production.forEach(prod => {
                const prodRow = createContributorRow(prod.artist_name, prod.role, 'production');
                productionContainer.appendChild(prodRow);
            });
        }

        const performers = track.artists.filter(a => a.type === 'contributor' && performerRoles.includes(a.role));
        console.log('Performers found:', performers);

        const performersContainer = document.getElementById('performersContainer');
        if (performersContainer && performers.length > 0) {
            performersContainer.innerHTML = '';
            performers.forEach(perf => {
                const perfRow = createContributorRow(perf.artist_name, perf.role, 'performer');
                performersContainer.appendChild(perfRow);
            });
        }
    }

    // If audio file exists, show file info
    if (track.audio_file_path) {
        const dropZone = document.getElementById('dropZone');
        const waveformContainer = document.getElementById('waveformContainer');
        const audioFileName = document.getElementById('audioFileName');

        if (dropZone) dropZone.style.display = 'none';
        if (waveformContainer) waveformContainer.style.display = 'block';

        if (audioFileName) {
            const fileName = track.audio_file_path.split('/').pop();
            audioFileName.textContent = fileName;
            audioFileName.classList.remove('text-muted');
            audioFileName.classList.add('text-success');
        }

        // Load the audio file into waveform
        const audioUrl = `../${track.audio_file_path}`;
        console.log('Loading audio from:', audioUrl);

        // Initialize waveform if not already done
        if (!wavesurfer && document.getElementById('waveform')) {
            try {
                wavesurfer = WaveSurfer.create({
                    container: '#waveform',
                    waveColor: '#dc3545',
                    progressColor: '#c82333',
                    cursorColor: '#333',
                    barWidth: 2,
                    barHeight: 1,
                    barRadius: 2,
                    barGap: 2,
                    responsive: true,
                    normalize: true
                });

                wavesurfer.on('ready', () => {
                    console.log('Existing track audio loaded');
                });

                wavesurfer.on('error', (error) => {
                    console.error('WaveSurfer error:', error);
                });
            } catch (error) {
                console.error('Error creating waveform:', error);
            }
        }

        // Load the audio
        if (wavesurfer) {
            wavesurfer.load(audioUrl);
        }
    }

    console.log('Track data loaded successfully');
}

/**
 * Load partner stores and check the appropriate checkboxes
 */
function loadPartnerStores(stores) {
    console.log('Loading partner stores:', stores);

    // Uncheck all partner checkboxes first
    const allPartnerCheckboxes = document.querySelectorAll('input[value="partners-check"]');
    allPartnerCheckboxes.forEach(checkbox => checkbox.checked = false);

    // Check the boxes for selected stores
    stores.forEach(store => {
        // Find the checkbox by looking for the h6 with the store name
        const storeHeaders = document.querySelectorAll('.partner-box h6');
        storeHeaders.forEach(header => {
            if (header.textContent.trim() === store.store_name) {
                // Find the checkbox in the same parent
                const checkbox = header.closest('.partner-box').querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = true;
                    console.log('Checked partner:', store.store_name);
                }
            }
        });
    });
}

/**
 * Create artist row element
 */
function createArtistRow(name, role) {
    const artistRow = document.createElement('div');
    artistRow.className = 'mt-3 d-flex align-items-center artist-row';

    console.log('Creating artist row with name:', name, 'role:', role);

    // Build options for artist select
    let artistOptions = '<option value="">Select artist</option>';
    artistsList.forEach(artist => {
        const selected = artist.name === name ? 'selected' : '';
        artistOptions += `<option value="${escapeHtml(artist.name)}" ${selected}>${escapeHtml(artist.name)}</option>`;
    });
    // Add current name if not in list
    if (name && !artistsList.find(a => a.name === name)) {
        artistOptions += `<option value="${escapeHtml(name)}" selected>${escapeHtml(name)}</option>`;
    }

    // Normalize role for comparison
    const normalizedRole = role ? role.toLowerCase().replace(/[\s-]+/g, '-') : 'primary-artist';

    artistRow.innerHTML = `
        <div class="bg-grey p-3 row w-100 me-3">
            <div class="col-lg-4">
                <label class="label">Artist name:</label>
                <div class="input-group">
                    <select class="form-select artist-name artist-select">
                        ${artistOptions}
                    </select>
                    <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="label">Role:</label>
                <select class="form-select artist-role">
                    <option value="primary-artist" ${normalizedRole === 'primary-artist' ? 'selected' : ''}>Primary artist</option>
                    <option value="featured-artist" ${normalizedRole === 'featured-artist' ? 'selected' : ''}>Featured artist</option>
                    <option value="remixer-artist" ${normalizedRole === 'remixer-artist' || normalizedRole === 'remixer' ? 'selected' : ''}>Remixer</option>
                </select>
            </div>
        </div>
        <div class="text-danger remove-artist" style="cursor: pointer;">
            <i class="bi bi-trash3"></i>
        </div>
    `;

    // Add remove handler
    artistRow.querySelector('.remove-artist').addEventListener('click', () => {
        artistRow.remove();
    });

    return artistRow;
}

/**
 * Create contributor row element
 */
function createContributorRow(name, role, type) {
    const row = document.createElement('div');

    // Set correct class based on type
    let rowClass = 'contributor-row';
    let nameClass = 'contributor-name';
    let roleClass = 'contributor-role';
    let removeClass = 'remove-contributor';

    if (type === 'writer') {
        rowClass = 'writer-row';
        nameClass = 'writer-name';
        roleClass = 'writer-role';
        removeClass = 'remove-writer';
    } else if (type === 'production') {
        rowClass = 'production-row';
        nameClass = 'production-name';
        roleClass = 'production-role';
        removeClass = 'remove-production';
    } else if (type === 'performer') {
        rowClass = 'performer-row';
        nameClass = 'performer-name';
        roleClass = 'performer-role';
        removeClass = 'remove-performer';
    }

    row.className = `mt-3 d-flex align-items-center ${rowClass}`;

    console.log('Creating contributor row - name:', name, 'role:', role, 'type:', type);

    // Normalize role for comparison (convert to lowercase and replace hyphens with spaces)
    const normalizedRole = role ? role.toLowerCase().replace(/-/g, ' ') : '';
    console.log('Normalized role:', normalizedRole);

    // Build artist options
    let artistOptions = '<option value="">Select artist</option>';
    artistsList.forEach(artist => {
        const selected = artist.name === name ? 'selected' : '';
        artistOptions += `<option value="${escapeHtml(artist.name)}" ${selected}>${escapeHtml(artist.name)}</option>`;
    });
    // Add current name if not in list
    if (name && !artistsList.find(a => a.name === name)) {
        artistOptions += `<option value="${escapeHtml(name)}" selected>${escapeHtml(name)}</option>`;
    }

    let roleOptions = '';
    if (type === 'writer') {
        roleOptions = `
            <option value="arranger" ${normalizedRole === 'arranger' ? 'selected' : ''}>Arranger</option>
            <option value="composer" ${normalizedRole === 'composer' ? 'selected' : ''}>Composer</option>
            <option value="librettist" ${normalizedRole === 'librettist' ? 'selected' : ''}>Librettist</option>
            <option value="lyricist" ${normalizedRole === 'lyricist' ? 'selected' : ''}>Lyricist</option>
            <option value="songwriter" ${normalizedRole === 'songwriter' ? 'selected' : ''}>Songwriter</option>
            <option value="transcriber" ${normalizedRole === 'transcriber' ? 'selected' : ''}>Transcriber</option>
            <option value="vocal-adaptation" ${normalizedRole === 'vocal adaptation' ? 'selected' : ''}>Vocal Adaptation</option>
        `;
    } else if (type === 'production') {
        roleOptions = `
            <option value="assistant-engineer" ${normalizedRole === 'assistant engineer' ? 'selected' : ''}>Assistant Engineer</option>
            <option value="co-producer" ${normalizedRole === 'co producer' ? 'selected' : ''}>Co-Producer</option>
            <option value="graphic-design" ${normalizedRole === 'graphic design' ? 'selected' : ''}>Graphic Design</option>
            <option value="guitar-technician" ${normalizedRole === 'guitar technician' ? 'selected' : ''}>Guitar Technician</option>
            <option value="mastering-engineer" ${normalizedRole === 'mastering engineer' ? 'selected' : ''}>Mastering Engineer</option>
            <option value="mixing-engineer" ${normalizedRole === 'mixing engineer' ? 'selected' : ''}>Mixing Engineer</option>
            <option value="producer" ${normalizedRole === 'producer' ? 'selected' : ''}>Producer</option>
            <option value="production-assistant" ${normalizedRole === 'production assistant' ? 'selected' : ''}>Production Assistant</option>
            <option value="recording-engineer" ${normalizedRole === 'recording engineer' ? 'selected' : ''}>Recording Engineer</option>
            <option value="sound-engineer" ${normalizedRole === 'sound engineer' ? 'selected' : ''}>Sound Engineer</option>
            <option value="vocal-engineer" ${normalizedRole === 'vocal engineer' ? 'selected' : ''}>Vocal Engineer</option>
        `;
    } else if (type === 'performer') {
        roleOptions = `
            <option value="background-vocalist" ${normalizedRole === 'background vocalist' ? 'selected' : ''}>Background Vocalist</option>
            <option value="bass" ${normalizedRole === 'bass' ? 'selected' : ''}>Bass</option>
            <option value="cello" ${normalizedRole === 'cello' ? 'selected' : ''}>Cello</option>
            <option value="drums" ${normalizedRole === 'drums' ? 'selected' : ''}>Drums</option>
            <option value="guitar" ${normalizedRole === 'guitar' ? 'selected' : ''}>Guitar</option>
            <option value="keyboard" ${normalizedRole === 'keyboard' ? 'selected' : ''}>Keyboard</option>
            <option value="percussion" ${normalizedRole === 'percussion' ? 'selected' : ''}>Percussion</option>
            <option value="piano" ${normalizedRole === 'piano' ? 'selected' : ''}>Piano</option>
            <option value="saxophone" ${normalizedRole === 'saxophone' ? 'selected' : ''}>Saxophone</option>
            <option value="trumpet" ${normalizedRole === 'trumpet' ? 'selected' : ''}>Trumpet</option>
            <option value="violin" ${normalizedRole === 'violin' ? 'selected' : ''}>Violin</option>
            <option value="vocals" ${normalizedRole === 'vocals' ? 'selected' : ''}>Vocals</option>
        `;
    }

    row.innerHTML = `
        <div class="bg-grey p-3 row w-100 me-3">
            <div class="col-lg-4">
                <label class="label">Artist name:</label>
                <div class="input-group">
                    <select class="form-select ${nameClass} artist-select">
                        ${artistOptions}
                    </select>
                    <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="label">Role:</label>
                <select class="form-select ${roleClass}">
                    ${roleOptions}
                </select>
            </div>
        </div>
        <div class="text-danger ${removeClass}" style="cursor: pointer;">
            <i class="bi bi-trash3"></i>
        </div>
    `;

    // Add remove handler
    row.querySelector(`.${removeClass}`).addEventListener('click', () => {
        row.remove();
    });

    return row;
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Handle audio file change
 */
function handleAudioFileChange(e) {
    const file = e.target.files[0];
    if (file) {
        const fileNameDiv = document.getElementById('audioFileName');
        if (fileNameDiv) {
            fileNameDiv.textContent = `Selected: ${file.name}`;
            fileNameDiv.classList.remove('text-muted');
            fileNameDiv.classList.add('text-success');
        }
    }
}

/**
 * Save track
 */
async function saveTrack() {
    console.log('Save track clicked');

    // Check if audio is still uploading
    if (isAudioUploading) {
        showError('Please wait for the audio file to finish uploading.');
        return;
    }

    // Get form data
    const trackData = getTrackFormData();
    console.log('Track data:', trackData);

    // Validate
    if (!validateTrackData(trackData)) {
        console.log('Validation failed');
        return;
    }

    // Show loading
    const saveBtn = document.getElementById('saveBtn');
    if (!saveBtn) {
        console.error('Save button not found');
        showError('Save button not found');
        return;
    }

    console.log('Starting save process...');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    try {
        // Create FormData for file upload
        const formData = new FormData();
        formData.append('track_number', 1); // Single track for now
        formData.append('track_title', trackData.songName);
        formData.append('track_version', trackData.version);
        formData.append('isrc', '');
        formData.append('audio_style', trackData.audioStyle);
        formData.append('language', trackData.language);
        formData.append('explicit_content', trackData.explicitStatus);
        formData.append('preview_start', trackData.previewStart);
        formData.append('recording_year', trackData.recordingYear);
        formData.append('recording_country', trackData.recordingCountry);
        formData.append('release_date', trackData.releaseDate);
        formData.append('release_time', trackData.releaseTime);
        formData.append('worldwide_release', trackData.worldwideRelease);

        console.log('=== TRACK DATA BEING SENT ===');
        console.log('Recording Year:', trackData.recordingYear);
        console.log('Recording Country:', trackData.recordingCountry);
        console.log('Song Name:', trackData.songName);
        console.log('Language:', trackData.language);
        console.log('Audio Style:', trackData.audioStyle);
        console.log('Explicit Status:', trackData.explicitStatus);
        console.log('Release Date:', trackData.releaseDate);
        console.log('Release Time:', trackData.releaseTime);
        console.log('Worldwide Release:', trackData.worldwideRelease);

        // Collect display artists
        const displayArtists = [];
        const displayArtistRows = document.querySelectorAll('#displayArtistsContainer .artist-row');
        displayArtistRows.forEach(row => {
            const name = row.querySelector('.artist-name')?.value?.trim();
            const role = row.querySelector('.artist-role')?.value;
            if (name) {
                displayArtists.push({ name, role, type: 'display' });
            }
        });
        formData.append('display_artists', JSON.stringify(displayArtists));

        // Collect writers
        const writers = [];
        const writerRows = document.querySelectorAll('#writersContainer .writer-row, #writersContainer .contributor-row');
        writerRows.forEach(row => {
            const name = row.querySelector('.writer-name, .contributor-name')?.value?.trim();
            const role = row.querySelector('.writer-role, .contributor-role')?.value;
            if (name) {
                writers.push({ name, role, type: 'contributor', category: 'writer' });
            }
        });
        formData.append('writers', JSON.stringify(writers));

        // Collect production
        const production = [];
        const productionRows = document.querySelectorAll('#productionContainer .production-row, #productionContainer .contributor-row');
        productionRows.forEach(row => {
            const name = row.querySelector('.production-name, .contributor-name')?.value?.trim();
            const role = row.querySelector('.production-role, .contributor-role')?.value;
            if (name) {
                production.push({ name, role, type: 'contributor', category: 'production' });
            }
        });
        formData.append('production', JSON.stringify(production));

        // Collect performers
        const performers = [];
        const performerRows = document.querySelectorAll('#performersContainer .performer-row, #performersContainer .contributor-row');
        performerRows.forEach(row => {
            const name = row.querySelector('.performer-name, .contributor-name')?.value?.trim();
            const role = row.querySelector('.performer-role, .contributor-role')?.value;
            if (name) {
                performers.push({ name, role, type: 'contributor', category: 'performer' });
            }
        });
        formData.append('performers', JSON.stringify(performers));

        // Add uploaded audio path if available (from pre-upload)
        if (uploadedAudioPath) {
            formData.append('uploaded_audio_path', uploadedAudioPath);
        } else if (trackData.audioFile) {
            // Fallback: Add audio file directly if not pre-uploaded
            formData.append('audio_file', trackData.audioFile);
        }

        // Collect selected partner stores
        const selectedStores = [];
        const partnerCheckboxes = document.querySelectorAll('.partner-box input[type="checkbox"]:checked');
        partnerCheckboxes.forEach(checkbox => {
            const storeName = checkbox.closest('.partner-box').querySelector('h6')?.textContent?.trim();
            if (storeName) {
                selectedStores.push(storeName);
            }
        });
        formData.append('selected_stores', JSON.stringify(selectedStores));

        console.log('FormData prepared with artists:', {
            displayArtists,
            writers,
            production,
            performers,
            selectedStores
        });

        // Log all FormData entries for debugging
        console.log('FormData entries:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        let response;
        if (existingTrackId) {
            // Update existing track
            console.log('Updating track:', existingTrackId);
            response = await API.put(`tracks/${existingTrackId}`, formData, true);
        } else {
            // Create new track
            console.log('Creating new track for release:', releaseId);
            response = await API.post(`releases/${releaseId}/tracks`, formData, true);
        }

        console.log('API response:', response);

        if (response.success) {
            showSuccess(`Track ${existingTrackId ? 'updated' : 'saved'} successfully!`);

            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

            // Don't redirect - reload the page data instead
            console.log('Reloading release details...');
            await loadReleaseDetails();

            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        } else {
            throw new Error(response.message || `Failed to ${existingTrackId ? 'update' : 'save'} track`);
        }

    } catch (error) {
        console.error('Error saving track:', error);
        showError(error.message || 'Failed to save track');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

/**
 * Get track form data
 */
function getTrackFormData() {
    // Get all form inputs
    const audioFileInput = document.getElementById('audioFile');
    const songNameInput = document.getElementById('songName');
    const versionInput = document.getElementById('trackVersion');
    const previewStartInput = document.getElementById('previewStart');
    const explicitStatus = document.querySelector('input[name="explicit-status"]:checked');
    const audioStyle = document.querySelector('input[name="audio-style"]:checked');
    const languageSelect = document.getElementById('language');
    const recordingYearSelect = document.getElementById('recordingYear');
    const recordingCountrySelect = document.getElementById('recordingCountry');
    const releaseDateInput = document.getElementById('releaseDate');
    const releaseTimeInput = document.getElementById('releaseTime');
    const worldwideReleaseToggle = document.getElementById('worldwideRelease');

    console.log('=== GETTING TRACK FORM DATA ===');
    console.log('Recording Year Select Element:', recordingYearSelect);
    console.log('Recording Year Value:', recordingYearSelect?.value);
    console.log('Recording Country Select Element:', recordingCountrySelect);
    console.log('Recording Country Value:', recordingCountrySelect?.value);
    console.log('Language Value:', languageSelect?.value);
    console.log('Audio Style Value:', audioStyle?.value);
    console.log('Explicit Status Value:', explicitStatus?.value);
    console.log('Release Date:', releaseDateInput?.value);
    console.log('Release Time:', releaseTimeInput?.value);
    console.log('Worldwide Release:', worldwideReleaseToggle?.checked);

    const data = {
        audioFile: audioFileInput?.files[0],
        songName: songNameInput?.value?.trim() || '',
        version: versionInput?.value?.trim() || '',
        previewStart: previewStartInput?.value || 0,
        explicitStatus: explicitStatus?.value || 'no',
        audioStyle: audioStyle?.value || 'Vocal',
        language: languageSelect?.value || '',
        recordingYear: recordingYearSelect?.value || '',
        recordingCountry: recordingCountrySelect?.value || '',
        releaseDate: releaseDateInput?.value || '',
        releaseTime: releaseTimeInput?.value || '',
        worldwideRelease: worldwideReleaseToggle?.checked ? 1 : 0
    };

    console.log('Final track data object:', data);
    return data;
}

/**
 * Validate track data
 */
function validateTrackData(trackData) {
    const errors = [];

    if (!trackData.songName) {
        errors.push('Song name is required');
    }

    // Validate that song name matches release title
    if (trackData.songName && releaseData && releaseData.release_title) {
        const songNameLower = trackData.songName.toLowerCase().trim();
        const releaseTitleLower = releaseData.release_title.toLowerCase().trim();
        if (songNameLower !== releaseTitleLower) {
            errors.push('Song name must match the release title ("' + releaseData.release_title + '")');
        }
    }

    // Audio file is only required for new tracks (check both file and uploaded path)
    if (!trackData.audioFile && !uploadedAudioPath && !existingTrackId) {
        errors.push('Audio file is required');
    }

    if (!trackData.language) {
        errors.push('Recording language is required');
    }

    if (!trackData.recordingYear) {
        errors.push('Recording year is required');
    }

    if (!trackData.recordingCountry) {
        errors.push('Recording country is required');
    }

    if (errors.length > 0) {
        showError(errors.join('<br>'));
        return false;
    }

    return true;
}

/**
 * Show success message
 */
function showSuccess(message) {
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        alertContainer.innerHTML = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        window.scrollTo(0, 0);
    } else {
        alert(message);
    }
}

/**
 * Show error message
 */
function showError(message) {
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        alertContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        window.scrollTo(0, 0);
    } else {
        alert(message);
    }
}

/**
 * Setup Drag and Drop functionality
 */
function setupDragAndDrop() {
    console.log('Setting up drag and drop...');

    const dropZone = document.getElementById('dropZone');
    const audioFileInput = document.getElementById('audioFile');

    console.log('Drop zone:', dropZone);
    console.log('Audio file input:', audioFileInput);

    if (!dropZone || !audioFileInput) {
        console.log('Drop zone or audio file input not found!');
        return;
    }

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });

    // Handle dropped files
    dropZone.addEventListener('drop', handleDrop, false);

    // Handle file input change
    audioFileInput.addEventListener('change', (e) => {
        const files = e.target.files;
        if (files.length > 0) {
            handleFiles(files);
        }
    });

    // Setup waveform controls
    setupWaveformControls();
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files);
}

function handleFiles(files) {
    console.log('handleFiles called with:', files);

    if (files.length === 0) {
        console.log('No files selected');
        return;
    }

    const file = files[0];
    console.log('File selected:', file.name, 'Type:', file.type, 'Size:', file.size);

    // Validate file size (300MB limit)
    if (file.size > MAX_AUDIO_SIZE_BYTES) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        showError(`Audio file is too large (${sizeMB} MB). Maximum allowed size is ${MAX_AUDIO_SIZE_MB} MB.`);
        return;
    }

    // Validate file type - Only AIFF, FLAC, WAV allowed
    const validTypes = ['audio/wav', 'audio/x-wav', 'audio/flac', 'audio/x-flac', 'audio/aiff', 'audio/x-aiff'];
    const validExtensions = ['.wav', '.flac', '.aiff', '.aif'];
    const fileExtension = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();

    console.log('File extension:', fileExtension);
    console.log('File type:', file.type);

    if (!validTypes.includes(file.type) && !validExtensions.includes(fileExtension)) {
        console.log('Invalid file type');
        showError('Please upload a valid audio file (AIFF, FLAC, or WAV only)');
        return;
    }

    console.log('File is valid, proceeding...');

    // Store the file
    currentAudioFile = file;

    // Display file info
    displayAudioInfo(file);

    // Load waveform
    loadWaveform(file);

    // Start uploading the file immediately
    uploadAudioFile(file);
}

function displayAudioInfo(file) {
    const fileName = document.getElementById('audioFileName');
    const fileSize = document.getElementById('audioFileSize');

    if (fileName) {
        fileName.textContent = file.name;
    }

    if (fileSize) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        fileSize.textContent = `Size: ${sizeMB} MB`;
    }

    // Hide drop zone, show waveform container
    document.getElementById('dropZone').style.display = 'none';
    document.getElementById('waveformContainer').style.display = 'block';
}

/**
 * Upload audio file with progress tracking
 */
async function uploadAudioFile(file) {
    console.log('Starting audio file upload:', file.name);

    isAudioUploading = true;
    uploadedAudioPath = null;

    // Disable save button during upload
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading audio...';
    }

    // Show progress bar
    showUploadProgress(0);

    const formData = new FormData();
    formData.append('audio_file', file);

    try {
        const response = await uploadWithProgress(formData, (progress) => {
            showUploadProgress(progress);
        });

        if (response.success) {
            console.log('Audio uploaded successfully:', response.data);
            uploadedAudioPath = response.data.temp_file;
            showUploadProgress(100, true);
            showSuccess('Audio file uploaded successfully!');
        } else {
            throw new Error(response.message || 'Upload failed');
        }
    } catch (error) {
        console.error('Audio upload error:', error);
        showError('Failed to upload audio file: ' + error.message);
        showUploadProgress(0, false, true);

        // Reset audio selection
        resetAudioSelection();
    } finally {
        isAudioUploading = false;

        // Re-enable save button
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Submit Release';
        }
    }
}

/**
 * Upload with XMLHttpRequest for progress tracking
 */
function uploadWithProgress(formData, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        // Get API URL
        const apiUrl = (typeof AppConfig !== 'undefined')
            ? AppConfig.apiUrl
            : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
                ? 'http://localhost/trinity/api'
                : 'https://trinity.futurewebhost.com.ng/api';

        xhr.open('POST', `${apiUrl}/audio-upload`, true);

        // Add auth token if available
        const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
        if (token) {
            xhr.setRequestHeader('Authorization', `Bearer ${token}`);
        }

        // Track upload progress
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                onProgress(percentComplete);
            }
        };

        xhr.onload = () => {
            try {
                const response = JSON.parse(xhr.responseText);
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(response);
                } else {
                    reject(new Error(response.message || 'Upload failed'));
                }
            } catch (e) {
                reject(new Error('Invalid server response'));
            }
        };

        xhr.onerror = () => {
            reject(new Error('Network error during upload'));
        };

        xhr.send(formData);
    });
}

/**
 * Show upload progress bar
 */
function showUploadProgress(percent, complete = false, error = false) {
    let progressContainer = document.getElementById('uploadProgressContainer');

    // Create progress container if it doesn't exist
    if (!progressContainer) {
        progressContainer = document.createElement('div');
        progressContainer.id = 'uploadProgressContainer';
        progressContainer.className = 'mt-3';
        progressContainer.innerHTML = `
            <div class="d-flex justify-content-between mb-1">
                <span id="uploadProgressText">Uploading...</span>
                <span id="uploadProgressPercent">0%</span>
            </div>
            <div class="progress" style="height: 20px;">
                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger"
                     role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        `;

        // Insert after waveform container
        const waveformContainer = document.getElementById('waveformContainer');
        if (waveformContainer) {
            waveformContainer.appendChild(progressContainer);
        }
    }

    const progressBar = document.getElementById('uploadProgressBar');
    const progressPercent = document.getElementById('uploadProgressPercent');
    const progressText = document.getElementById('uploadProgressText');

    if (progressBar) {
        progressBar.style.width = `${percent}%`;
        progressBar.setAttribute('aria-valuenow', percent);

        if (complete) {
            progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            progressBar.classList.add('bg-success');
            progressBar.classList.remove('bg-danger');
        } else if (error) {
            progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped', 'bg-danger');
            progressBar.classList.add('bg-warning');
        }
    }

    if (progressPercent) {
        progressPercent.textContent = `${percent}%`;
    }

    if (progressText) {
        if (complete) {
            progressText.textContent = 'Upload complete!';
        } else if (error) {
            progressText.textContent = 'Upload failed';
        } else {
            progressText.textContent = 'Uploading...';
        }
    }
}

/**
 * Reset audio selection
 */
function resetAudioSelection() {
    currentAudioFile = null;
    uploadedAudioPath = null;

    if (wavesurfer) {
        wavesurfer.destroy();
        wavesurfer = null;
    }

    const dropZone = document.getElementById('dropZone');
    const waveformContainer = document.getElementById('waveformContainer');
    const audioFileInput = document.getElementById('audioFile');

    if (dropZone) dropZone.style.display = 'block';
    if (waveformContainer) waveformContainer.style.display = 'none';
    if (audioFileInput) audioFileInput.value = '';

    // Remove progress bar
    const progressContainer = document.getElementById('uploadProgressContainer');
    if (progressContainer) {
        progressContainer.remove();
    }
}

function loadWaveform(file) {
    console.log('Loading waveform for file:', file.name);

    // Check if WaveSurfer is available
    if (typeof WaveSurfer === 'undefined') {
        console.error('WaveSurfer is not loaded!');
        showError('Audio player library not loaded. Please refresh the page.');
        return;
    }

    try {
        // Destroy existing waveform if any
        if (wavesurfer) {
            wavesurfer.destroy();
        }

        console.log('Creating WaveSurfer instance...');

        // Create new waveform
        wavesurfer = WaveSurfer.create({
            container: '#waveform',
            waveColor: '#dc3545',
            progressColor: '#c82333',
            cursorColor: '#333',
            barWidth: 2,
            barRadius: 3,
            cursorWidth: 1,
            height: 100,
            barGap: 2,
            responsive: true,
            normalize: true
        });

        console.log('WaveSurfer instance created');

        // Load audio file
        const fileURL = URL.createObjectURL(file);
        console.log('Loading audio from URL:', fileURL);
        wavesurfer.load(fileURL);

        // Update duration when ready
        wavesurfer.on('ready', () => {
            console.log('Waveform ready');
            const duration = wavesurfer.getDuration();
            document.getElementById('duration').textContent = formatTime(duration);
        });

        // Update current time during playback
        wavesurfer.on('audioprocess', () => {
            const currentTime = wavesurfer.getCurrentTime();
            document.getElementById('currentTime').textContent = formatTime(currentTime);
        });

        // Reset play button when finished
        wavesurfer.on('finish', () => {
            const playPauseBtn = document.getElementById('playPauseBtn');
            playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> Play';
        });

        // Handle errors
        wavesurfer.on('error', (error) => {
            console.error('WaveSurfer error:', error);
            showError('Error loading audio file: ' + error);
        });

    } catch (error) {
        console.error('Error creating waveform:', error);
        showError('Error loading audio player: ' + error.message);
    }
}

function setupWaveformControls() {
    // Play/Pause button
    const playPauseBtn = document.getElementById('playPauseBtn');
    if (playPauseBtn) {
        playPauseBtn.addEventListener('click', () => {
            if (!wavesurfer) return;

            if (wavesurfer.isPlaying()) {
                wavesurfer.pause();
                playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> Play';
            } else {
                wavesurfer.play();
                playPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
            }
        });
    }

    // Stop button
    const stopBtn = document.getElementById('stopBtn');
    if (stopBtn) {
        stopBtn.addEventListener('click', () => {
            if (!wavesurfer) return;
            wavesurfer.stop();
            const playPauseBtn = document.getElementById('playPauseBtn');
            playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> Play';
        });
    }

    // Volume slider
    const volumeSlider = document.getElementById('volumeSlider');
    if (volumeSlider) {
        volumeSlider.addEventListener('input', (e) => {
            if (!wavesurfer) return;
            const volume = e.target.value / 100;
            wavesurfer.setVolume(volume);
        });
    }

    // Remove audio button
    const removeAudioBtn = document.getElementById('removeAudioBtn');
    if (removeAudioBtn) {
        removeAudioBtn.addEventListener('click', () => {
            resetAudioSelection();
        });
    }
}

function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Add Display Artist
 */
function addDisplayArtist() {
    console.log('Adding display artist...');

    const container = document.getElementById('displayArtistsContainer');
    if (!container) {
        console.error('Display artists container not found');
        return;
    }

    const artistRow = document.createElement('div');
    artistRow.className = 'mt-3 d-flex align-items-center artist-row';
    artistRow.innerHTML = `
        <div class="bg-grey p-3 row w-100 me-3">
            <div class="col-lg-4">
                <label class="label">Artist name: <i class="bi bi-question-circle custom-tip" title="Select an artist or add a new one."></i></label>
                <div class="input-group">
                    <select class="form-select artist-name artist-select">
                        <option value="">Select artist</option>
                        ${artistsList.map(a => `<option value="${escapeHtml(a.name)}">${escapeHtml(a.name)}</option>`).join('')}
                    </select>
                    <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="label">Role: <i class="bi bi-question-circle custom-tip" title="Select the artist's role."></i></label>
                <select class="form-select artist-role" name="role">
                    <option value="primary-artist">Primary artist</option>
                    <option value="featured-artist">Featured artist</option>
                    <option value="remixer-artist">Remixer</option>
                </select>
            </div>
        </div>
        <div class="text-danger remove-artist" style="cursor: pointer;"><i class="bi bi-trash3"></i></div>
    `;

    container.appendChild(artistRow);

    // Setup remove button for the new row
    const removeBtn = artistRow.querySelector('.remove-artist');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            artistRow.remove();
        });
    }

    console.log('Display artist added');
}

/**
 * Add Writer
 */
function addWriter() {
    console.log('Adding writer...');

    const container = document.getElementById('writersContainer');
    if (!container) {
        console.error('Writers container not found');
        return;
    }

    const writerRow = document.createElement('div');
    writerRow.className = 'mt-3 d-flex align-items-center writer-row';
    writerRow.innerHTML = `
        <div class="bg-grey p-3 row w-100 me-3">
            <div class="col-lg-4">
                <label class="label">Artist name: <i class="bi bi-question-circle custom-tip" title="Select or add a writer."></i></label>
                <div class="input-group">
                    <select class="form-select writer-name artist-select">
                        <option value="">Select artist</option>
                        ${artistsList.map(a => `<option value="${escapeHtml(a.name)}">${escapeHtml(a.name)}</option>`).join('')}
                    </select>
                    <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="label">Role: <i class="bi bi-question-circle custom-tip" title="Select the writer's role."></i></label>
                <select class="form-select writer-role" name="role">
                    <option value="arranger">Arranger</option>
                    <option value="composer">Composer</option>
                    <option value="librettist">Librettist</option>
                    <option value="lyricist">Lyricist</option>
                    <option value="songwriter">Songwriter</option>
                    <option value="transcriber">Transcriber</option>
                    <option value="vocal-adaptation">Vocal Adaptation</option>
                </select>
            </div>
        </div>
        <div class="text-danger remove-writer" style="cursor: pointer;"><i class="bi bi-trash3"></i></div>
    `;

    container.appendChild(writerRow);

    // Setup remove button for the new row
    const removeBtn = writerRow.querySelector('.remove-writer');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            writerRow.remove();
        });
    }

    console.log('Writer added');
}

/**
 * Add Production & Engineering
 */
function addProduction() {
    console.log('Adding production & engineering...');

    const container = document.getElementById('productionContainer');
    if (!container) {
        console.error('Production container not found');
        return;
    }

    const productionRow = document.createElement('div');
    productionRow.className = 'mt-3 d-flex align-items-center production-row';
    productionRow.innerHTML = `
        <div class="bg-grey p-3 row w-100 me-3">
            <div class="col-lg-4">
                <label class="label">Artist name: <i class="bi bi-question-circle custom-tip" title="Select or add a production/engineering artist."></i></label>
                <div class="input-group">
                    <select class="form-select production-name artist-select">
                        <option value="">Select artist</option>
                        ${artistsList.map(a => `<option value="${escapeHtml(a.name)}">${escapeHtml(a.name)}</option>`).join('')}
                    </select>
                    <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="label">Role: <i class="bi bi-question-circle custom-tip" title="Select the production/engineering role."></i></label>
                <select class="form-select production-role" name="role">
                    <option value="assistant-engineer">Assistant Engineer</option>
                    <option value="co-producer">Co-Producer</option>
                    <option value="graphic-design">Graphic Design</option>
                    <option value="guitar-technician">Guitar Technician</option>
                    <option value="mastering-engineer">Mastering Engineer</option>
                    <option value="mixing-engineer">Mixing Engineer</option>
                    <option value="producer">Producer</option>
                    <option value="production-assistant">Production Assistant</option>
                    <option value="recording-engineer">Recording Engineer</option>
                    <option value="sound-engineer">Sound Engineer</option>
                    <option value="vocal-engineer">Vocal Engineer</option>
                </select>
            </div>
        </div>
        <div class="text-danger remove-production" style="cursor: pointer;"><i class="bi bi-trash3"></i></div>
    `;

    container.appendChild(productionRow);

    // Setup remove button for the new row
    const removeBtn = productionRow.querySelector('.remove-production');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            productionRow.remove();
        });
    }

    console.log('Production & Engineering added');
}

/**
 * Add Performer
 */
function addPerformer() {
    console.log('Adding performer...');

    const container = document.getElementById('performersContainer');
    if (!container) {
        console.error('Performers container not found');
        return;
    }

    const performerRow = document.createElement('div');
    performerRow.className = 'mt-3 d-flex align-items-center performer-row';
    performerRow.innerHTML = `
        <div class="bg-grey p-3 row w-100 me-3">
            <div class="col-lg-4">
                <label class="label">Artist name: <i class="bi bi-question-circle custom-tip" title="Select or add a performer."></i></label>
                <div class="input-group">
                    <select class="form-select performer-name artist-select">
                        <option value="">Select artist</option>
                        ${artistsList.map(a => `<option value="${escapeHtml(a.name)}">${escapeHtml(a.name)}</option>`).join('')}
                    </select>
                    <button type="button" class="btn btn-outline-danger add-new-artist-btn" title="Add new artist">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="label">Role: <i class="bi bi-question-circle custom-tip" title="Select the performer's role."></i></label>
                <select class="form-select performer-role" name="role">
                    <option value="background-vocalist">Background Vocalist</option>
                    <option value="bass">Bass</option>
                    <option value="cello">Cello</option>
                    <option value="drums">Drums</option>
                    <option value="guitar">Guitar</option>
                    <option value="keyboard">Keyboard</option>
                    <option value="percussion">Percussion</option>
                    <option value="piano">Piano</option>
                    <option value="saxophone">Saxophone</option>
                    <option value="trumpet">Trumpet</option>
                    <option value="violin">Violin</option>
                    <option value="vocals">Vocals</option>
                </select>
            </div>
        </div>
        <div class="text-danger remove-performer" style="cursor: pointer;"><i class="bi bi-trash3"></i></div>
    `;

    container.appendChild(performerRow);

    // Setup remove button for the new row
    const removeBtn = performerRow.querySelector('.remove-performer');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            performerRow.remove();
        });
    }

    console.log('Performer added');
}

/**
 * Setup remove buttons for existing artists/contributors
 */
function setupRemoveButtons() {
    console.log('Setting up remove buttons...');

    // Remove artist buttons
    document.querySelectorAll('.remove-artist').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('.artist-row');
            if (row) {
                row.remove();
            }
        });
    });

    // Remove writer buttons
    document.querySelectorAll('.remove-writer').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('.writer-row');
            if (row) {
                row.remove();
            }
        });
    });

    // Remove production buttons
    document.querySelectorAll('.remove-production').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('.production-row');
            if (row) {
                row.remove();
            }
        });
    });

    // Remove performer buttons
    document.querySelectorAll('.remove-performer').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('.performer-row');
            if (row) {
                row.remove();
            }
        });
    });

    console.log('Remove buttons setup complete');
}

/**
 * Setup Split Share functionality
 */
function setupSplitShare() {
    console.log('Setting up split share functionality...');

    // Handle split share form submission
    const splitShareForm = document.getElementById('splitShareForm');
    if (splitShareForm) {
        splitShareForm.addEventListener('submit', handleSplitShareSubmit);
    }

    // Load existing invitations when tab is shown
    const splitShareTab = document.getElementById('split-share-tab');
    if (splitShareTab) {
        splitShareTab.addEventListener('shown.bs.tab', loadSplitShareInvitations);
    }
}

/**
 * Handle split share form submission
 */
async function handleSplitShareSubmit(e) {
    e.preventDefault();

    const emailInput = document.getElementById('inviteeEmail');
    const percentageInput = document.getElementById('splitPercentage');

    const email = emailInput ? emailInput.value.trim() : '';
    const percentage = percentageInput ? parseFloat(percentageInput.value) : 0;

    // Validation
    if (!email || isNaN(percentage) || percentage <= 0) {
        showError('Please fill in all required fields');
        return;
    }

    if (percentage <= 0 || percentage > 100) {
        showError('Split percentage must be between 0.01 and 100');
        return;
    }

    // Calculate current total split percentage
    const currentTotal = await getCurrentTotalSplit();
    if (currentTotal + percentage > 100) {
        showError(`Total split percentage cannot exceed 100%. Current total: ${currentTotal}%. Maximum you can add: ${100 - currentTotal}%`);
        return;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('Please enter a valid email address');
        return;
    }

    try {
        const response = await API.post('split-shares', {
            release_id: releaseId,
            invitee_email: email,
            split_percentage: percentage
        });

        if (response.success) {
            showSuccess(response.message || 'Invitation sent successfully!');
            emailInput.value = '';
            percentageInput.value = '';
            loadSplitShareInvitations();
        } else {
            showError(response.message || 'Failed to send invitation');
        }
    } catch (error) {
        console.error('Error sending invitation:', error);
        showError(error.message || 'Failed to send invitation');
    }
}

/**
 * Get current total split percentage for this release
 */
async function getCurrentTotalSplit() {
    try {
        const response = await API.get(`split-shares/${releaseId}`);
        if (response.success && response.data.invitations) {
            return response.data.invitations.reduce((total, inv) => {
                return total + parseFloat(inv.split_percentage || 0);
            }, 0);
        }
    } catch (error) {
        console.error('Error getting current split total:', error);
    }
    return 0;
}

/**
 * Load split share invitations
 */
async function loadSplitShareInvitations() {
    console.log('Loading split share invitations for release:', releaseId);

    try {
        const response = await API.get(`split-shares/${releaseId}`);

        if (response.success) {
            const owner = response.data.owner || null;
            const invitations = response.data.invitations || [];
            displayInvitations(invitations, owner);
        } else {
            console.error('Failed to load invitations:', response.message);
        }
    } catch (error) {
        console.error('Error loading invitations:', error);
    }
}

/**
 * Display invitations
 */
function displayInvitations(invitations, owner) {
    const ownerContainer = document.getElementById('ownerSplitContainer');
    const pendingContainer = document.getElementById('pendingInvitationsContainer');
    const approvedContainer = document.getElementById('approvedSplitsContainer');

    // Display owner info
    if (ownerContainer && owner) {
        ownerContainer.innerHTML = `
            <div class="invitation-card owner h-100" style="border-left: 4px solid #198754; background-color: #f8f9fa;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1"><i class="bi bi-person-fill text-success"></i> ${escapeHtml(owner.name)}</h6>
                        <p class="mb-0 text-muted small">Split: <strong class="text-success">${owner.percentage}%</strong></p>
                        <p class="mb-0 text-muted small">${escapeHtml(owner.email)}</p>
                    </div>
                    <span class="badge bg-success">Owner</span>
                </div>
            </div>
        `;
    }

    const pending = invitations.filter(inv => inv.status === 'pending');
    const approved = invitations.filter(inv => inv.status === 'accepted');

    // Display pending invitations
    if (pending.length === 0) {
        pendingContainer.innerHTML = `
            <div class="col-12 text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p>No pending invitations</p>
            </div>
        `;
    } else {
        pendingContainer.innerHTML = pending.map(inv => `
            <div class="col-12 col-md-6 col-xl-4 mb-3">
                <div class="invitation-card">
                    <div class="d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${escapeHtml(inv.invitee_email)}</h6>
                                <p class="mb-0 text-muted small">Split: <strong>${inv.split_percentage}%</strong></p>
                                <p class="mb-0 text-muted small">Sent: ${formatDate(inv.created_at)}</p>
                            </div>
                            <span class="invitation-status pending">Pending</span>
                        </div>
                        <button class="btn btn-sm btn-outline-danger w-100" onclick="resendInvitation(${inv.id})">
                            <i class="bi bi-arrow-repeat"></i> Resend Invitation
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Display accepted splits
    if (approved.length === 0) {
        approvedContainer.innerHTML = `
            <div class="col-12 text-center text-muted py-4">
                <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                <p>No accepted splits yet</p>
            </div>
        `;
    } else {
        approvedContainer.innerHTML = approved.map(inv => `
            <div class="col-12 col-md-6 col-xl-4 mb-3">
                <div class="invitation-card accepted">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${escapeHtml(inv.invitee_email)}</h6>
                            <p class="mb-0 text-muted small">Split: <strong>${inv.split_percentage}%</strong></p>
                            <p class="mb-0 text-muted small">Accepted: ${formatDate(inv.updated_at)}</p>
                        </div>
                        <span class="invitation-status accepted">
                            <i class="bi bi-check-circle-fill"></i> Accepted
                        </span>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

/**
 * Resend invitation
 */
async function resendInvitation(invitationId) {
    try {
        const response = await API.post(`split-shares/${invitationId}/resend`);

        if (response.success) {
            showSuccess('Invitation resent successfully!');
        } else {
            showError(response.message || 'Failed to resend invitation');
        }
    } catch (error) {
        console.error('Error resending invitation:', error);
        showError('Failed to resend invitation');
    }
}

// End of file