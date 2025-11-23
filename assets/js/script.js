// Smart Delivery Zone Planner - Main JavaScript

// Global variables
let map;
let markers = [];
let geocoder;
let directionsService;
let directionsRenderer;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize application
function initializeApp() {
    // Initialize Google Maps if element exists
    if (document.getElementById('map')) {
        initializeMap();
    }
    
    // Initialize file upload handlers
    initializeFileUpload();
    
    // Initialize form handlers
    initializeForms();
    
    // Initialize tooltips
    initializeTooltips();
}

// Initialize Google Maps
function initializeMap() {
    // Default center (Bangkok, Thailand)
    const defaultCenter = { lat: 13.7563, lng: 100.5018 };
    
    map = new google.maps.Map(document.getElementById('map'), {
        zoom: 12,
        center: defaultCenter,
        styles: [
            {
                featureType: 'poi',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });
    
    geocoder = new google.maps.Geocoder();
    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        draggable: true,
        panel: document.getElementById('directions-panel')
    });
    
    directionsRenderer.setMap(map);
}

// File upload handler
function initializeFileUpload() {
    const fileInput = document.getElementById('file-upload');
    const dropZone = document.getElementById('drop-zone');
    
    if (fileInput && dropZone) {
        // Drag and drop events
        dropZone.addEventListener('dragover', handleDragOver);
        dropZone.addEventListener('drop', handleDrop);
        dropZone.addEventListener('dragleave', handleDragLeave);
        
        // File input change
        fileInput.addEventListener('change', handleFileSelect);
    }
}

// Handle drag over
function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.add('bg-blue-50', 'border-blue-300');
}

// Handle drag leave
function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('bg-blue-50', 'border-blue-300');
}

// Handle file drop
function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileSelect({ target: { files: files } });
    }
    
    e.currentTarget.classList.remove('bg-blue-50', 'border-blue-300');
}

// Handle file selection
function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        // Check file type
        const allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
        if (!allowedTypes.includes(file.type)) {
            showAlert('กรุณาเลือกไฟล์ Excel (.xlsx, .xls) หรือ CSV เท่านั้น', 'error');
            return;
        }
        
        // Display file info
        displayFileInfo(file);
        
        // Enable upload button
        const uploadBtn = document.getElementById('upload-btn');
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.classList.remove('opacity-50');
        }
    }
}

// Display file information
function displayFileInfo(file) {
    const fileInfoDiv = document.getElementById('file-info');
    if (fileInfoDiv) {
        fileInfoDiv.innerHTML = `
            <div class="flex items-center space-x-2 text-sm text-gray-600">
                <i class="fas fa-file-excel text-green-500"></i>
                <span>${file.name}</span>
                <span class="text-gray-400">(${formatFileSize(file.size)})</span>
            </div>
        `;
        fileInfoDiv.classList.remove('hidden');
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Initialize forms
function initializeForms() {
    // Address form submission
    const addressForm = document.getElementById('address-form');
    if (addressForm) {
        addressForm.addEventListener('submit', handleAddressSubmit);
    }
    
    // Zone form submission
    const zoneForm = document.getElementById('zone-form');
    if (zoneForm) {
        zoneForm.addEventListener('submit', handleZoneSubmit);
    }
}

// Provide a safe default zone submit handler so submission proceeds normally
function handleZoneSubmit(e) {
    // Intentionally do not prevent default so the form submits via POST
    // Optionally, add a lightweight UX improvement on submit
    try {
        const submitButton = e.target.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
    } catch (_) {
        // no-op
    }
}

// Handle address form submission
function handleAddressSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const address = formData.get('address');
    
    if (address) {
        geocodeAddress(address);
    }
}

// Geocode address
function geocodeAddress(address) {
    showLoading();
    
    geocoder.geocode({ address: address }, function(results, status) {
        hideLoading();
        
        if (status === 'OK') {
            const location = results[0].geometry.location;
            
            // Add marker
            addMarker(location, address);
            
            // Center map
            map.setCenter(location);
            map.setZoom(15);
            
            showAlert('แปลงที่อยู่เป็นพิกัดเรียบร้อย', 'success');
        } else {
            showAlert('ไม่สามารถแปลงที่อยู่เป็นพิกัดได้: ' + status, 'error');
        }
    });
}

// Add marker to map
function addMarker(location, title) {
    const marker = new google.maps.Marker({
        position: location,
        map: map,
        title: title,
        animation: google.maps.Animation.DROP
    });
    
    markers.push(marker);
    
    // Add info window
    const infoWindow = new google.maps.InfoWindow({
        content: `
            <div class="p-2">
                <h6 class="font-semibold">${title}</h6>
                <p class="text-sm text-gray-600">
                    Lat: ${location.lat().toFixed(6)}<br>
                    Lng: ${location.lng().toFixed(6)}
                </p>
            </div>
        `
    });
    
    marker.addListener('click', function() {
        infoWindow.open(map, marker);
    });
}

// Clear all markers
function clearMarkers() {
    markers.forEach(marker => marker.setMap(null));
    markers = [];
}

// Calculate route
function calculateRoute(waypoints) {
    if (waypoints.length < 2) {
        showAlert('ต้องมีจุดหมายปลายทางอย่างน้อย 2 จุด', 'error');
        return;
    }
    
    const origin = waypoints[0];
    const destination = waypoints[waypoints.length - 1];
    const waypointList = waypoints.slice(1, -1).map(point => ({
        location: point,
        stopover: true
    }));
    
    const request = {
        origin: origin,
        destination: destination,
        waypoints: waypointList,
        travelMode: google.maps.TravelMode.DRIVING,
        optimizeWaypoints: true
    };
    
    showLoading();
    
    directionsService.route(request, function(result, status) {
        hideLoading();
        
        if (status === 'OK') {
            directionsRenderer.setDirections(result);
            
            // Display route info
            displayRouteInfo(result);
        } else {
            showAlert('ไม่สามารถคำนวณเส้นทางได้: ' + status, 'error');
        }
    });
}

// Display route information
function displayRouteInfo(result) {
    const route = result.routes[0];
    const leg = route.legs[0];
    
    const routeInfoDiv = document.getElementById('route-info');
    if (routeInfoDiv) {
        routeInfoDiv.innerHTML = `
            <div class="bg-white p-4 rounded-lg shadow">
                <h4 class="font-semibold mb-2">ข้อมูลเส้นทาง</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">ระยะทาง:</span>
                        <span class="font-semibold">${leg.distance.text}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">เวลา:</span>
                        <span class="font-semibold">${leg.duration.text}</span>
                    </div>
                </div>
            </div>
        `;
    }
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'absolute bg-gray-800 text-white text-xs px-2 py-1 rounded shadow-lg z-50';
    tooltip.textContent = e.target.dataset.tooltip;
    tooltip.id = 'tooltip';
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
}

// Hide tooltip
function hideTooltip() {
    const tooltip = document.getElementById('tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Show loading
function showLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.classList.remove('hidden');
    } else {
        // Create loading element
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'loading';
        loadingDiv.className = 'loading';
        loadingDiv.innerHTML = '<div class="loading-spinner"></div>';
        document.body.appendChild(loadingDiv);
    }
}

// Hide loading
function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.classList.add('hidden');
    }
}

// Show alert
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 max-w-sm p-4 rounded-lg shadow-lg z-50 ${getAlertClass(type)}`;
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${getAlertIcon(type)} mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-auto">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// Get alert class based on type
function getAlertClass(type) {
    switch (type) {
        case 'success':
            return 'bg-green-100 border border-green-400 text-green-700';
        case 'error':
            return 'bg-red-100 border border-red-400 text-red-700';
        case 'warning':
            return 'bg-yellow-100 border border-yellow-400 text-yellow-700';
        default:
            return 'bg-blue-100 border border-blue-400 text-blue-700';
    }
}

// Get alert icon based on type
function getAlertIcon(type) {
    switch (type) {
        case 'success':
            return 'fa-check-circle';
        case 'error':
            return 'fa-exclamation-circle';
        case 'warning':
            return 'fa-exclamation-triangle';
        default:
            return 'fa-info-circle';
    }
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDistance(distance) {
    return (distance / 1000).toFixed(2) + ' กม.';
}

function formatDuration(duration) {
    const hours = Math.floor(duration / 3600);
    const minutes = Math.floor((duration % 3600) / 60);
    
    if (hours > 0) {
        return `${hours} ชั่วโมง ${minutes} นาที`;
    } else {
        return `${minutes} นาที`;
    }
} 