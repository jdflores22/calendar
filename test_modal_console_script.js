/**
 * Modal Conflict Detection Test Script
 * 
 * Instructions:
 * 1. Go to http://127.0.0.4:8000/calendar
 * 2. Log in if not already logged in
 * 3. Open browser console (F12)
 * 4. Copy and paste this entire script
 * 5. Run the test functions
 */

console.log('🧪 MODAL CONFLICT DETECTION TEST SCRIPT LOADED');
console.log('================================================');

// Test function to verify modal conflict detection
async function testModalConflictDetection() {
    console.log('🔍 Testing Modal Conflict Detection...');
    
    // Check if we're on the right page
    if (!window.location.pathname.includes('/calendar')) {
        console.error('❌ Please run this script on the calendar page: http://127.0.0.4:8000/calendar');
        return;
    }
    
    // Check if modal functions are available
    if (typeof window.checkAndDisplayConflicts !== 'function') {
        console.error('❌ Modal conflict detection functions not found. Make sure you\'re on the calendar page.');
        return;
    }
    
    console.log('✅ Modal functions found');
    
    // Check if modal elements exist
    const startInput = document.getElementById('start');
    const endInput = document.getElementById('end');
    const conflictStatus = document.getElementById('conflictStatus');
    const conflictDetails = document.getElementById('conflictDetails');
    
    console.log('📋 Element Check:');
    console.log('  - Start input:', !!startInput);
    console.log('  - End input:', !!endInput);
    console.log('  - Conflict status:', !!conflictStatus);
    console.log('  - Conflict details:', !!conflictDetails);
    
    if (!startInput || !endInput) {
        console.error('❌ Modal not open. Please open the event creation modal first.');
        console.log('💡 Click "New Event" button or click on a date in the calendar');
        return;
    }
    
    console.log('✅ Modal is open and elements found');
    
    // Test 1: Set test dates and trigger conflict check
    console.log('\n🧪 Test 1: Setting test dates...');
    const testStart = '2026-02-03T10:00';
    const testEnd = '2026-02-03T11:00';
    
    startInput.value = testStart;
    endInput.value = testEnd;
    
    console.log(`  - Start: ${testStart}`);
    console.log(`  - End: ${testEnd}`);
    
    // Trigger conflict detection
    console.log('  - Triggering conflict detection...');
    await window.checkAndDisplayConflicts();
    
    // Wait a moment for the API call to complete
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check results
    if (conflictStatus) {
        console.log('  - Conflict status updated:', conflictStatus.innerHTML.includes('Checking') ? 'Still loading...' : 'Complete');
    }
    
    console.log('✅ Test 1 complete');
    
    // Test 2: Test different time slot
    console.log('\n🧪 Test 2: Testing different time slot...');
    const testStart2 = '2026-02-04T14:00';
    const testEnd2 = '2026-02-04T15:00';
    
    startInput.value = testStart2;
    endInput.value = testEnd2;
    
    console.log(`  - Start: ${testStart2}`);
    console.log(`  - End: ${testEnd2}`);
    
    // Trigger conflict detection
    console.log('  - Triggering conflict detection...');
    await window.checkAndDisplayConflicts();
    
    // Wait for results
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    console.log('✅ Test 2 complete');
    
    console.log('\n📊 TESTING COMPLETE');
    console.log('===================');
    console.log('✅ Check the conflict detection panel on the right side of the modal');
    console.log('✅ Look for status changes (loading → results)');
    console.log('✅ Try different dates to see various conflict scenarios');
}

// Test function to directly call the API
async function testConflictAPI() {
    console.log('🔌 Testing Conflict Detection API directly...');
    
    const testData = {
        start: '2026-02-03T10:00:00',
        end: '2026-02-03T11:00:00'
    };
    
    console.log('📤 Sending request:', testData);
    
    try {
        const response = await fetch('/events/api/check-conflicts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(testData)
        });
        
        console.log('📥 Response status:', response.status, response.statusText);
        
        if (response.ok) {
            const result = await response.json();
            console.log('📋 API Result:', result);
            
            if (result.success) {
                if (result.has_conflicts) {
                    console.log('⚠️  CONFLICTS DETECTED:');
                    console.log(`   - Count: ${result.conflict_count}`);
                    console.log(`   - Can override: ${result.can_override}`);
                    if (result.conflicts) {
                        result.conflicts.forEach((conflict, index) => {
                            console.log(`   - Conflict ${index + 1}: ${conflict.title} (${conflict.start} to ${conflict.end})`);
                        });
                    }
                } else {
                    console.log('✅ NO CONFLICTS - Time slot is available');
                }
            } else {
                console.log('❌ API returned error:', result.message);
            }
        } else {
            const errorText = await response.text();
            console.error('❌ API Error:', errorText);
        }
        
    } catch (error) {
        console.error('❌ Network Error:', error);
    }
}

// Function to open modal and run tests
function openModalAndTest() {
    console.log('🚀 Opening modal and running tests...');
    
    // Try to open the modal
    if (typeof window.openEventModal === 'function') {
        window.openEventModal();
        console.log('✅ Modal opened');
        
        // Wait for modal to fully load, then run tests
        setTimeout(() => {
            testModalConflictDetection();
        }, 1000);
    } else {
        console.error('❌ openEventModal function not found. Please make sure you\'re on the calendar page.');
    }
}

// Function to debug modal state
function debugModalState() {
    console.log('🐛 DEBUGGING MODAL STATE');
    console.log('========================');
    
    const modal = document.getElementById('eventModal');
    const startInput = document.getElementById('start');
    const endInput = document.getElementById('end');
    const conflictStatus = document.getElementById('conflictStatus');
    const conflictDetails = document.getElementById('conflictDetails');
    
    console.log('Modal elements:');
    console.log('  - Modal container:', !!modal, modal ? (modal.classList.contains('hidden') ? '(hidden)' : '(visible)') : '');
    console.log('  - Start input:', !!startInput, startInput ? `(value: ${startInput.value})` : '');
    console.log('  - End input:', !!endInput, endInput ? `(value: ${endInput.value})` : '');
    console.log('  - Conflict status:', !!conflictStatus);
    console.log('  - Conflict details:', !!conflictDetails);
    
    console.log('\nGlobal functions:');
    console.log('  - openEventModal:', typeof window.openEventModal);
    console.log('  - closeEventModal:', typeof window.closeEventModal);
    console.log('  - checkAndDisplayConflicts:', typeof window.checkAndDisplayConflicts);
    console.log('  - checkConflicts:', typeof window.checkConflicts);
    
    console.log('\nPage info:');
    console.log('  - URL:', window.location.href);
    console.log('  - Path:', window.location.pathname);
}

// Instructions
console.log('\n📋 AVAILABLE TEST FUNCTIONS:');
console.log('============================');
console.log('• testModalConflictDetection() - Test modal conflict detection (modal must be open)');
console.log('• testConflictAPI() - Test the conflict detection API directly');
console.log('• openModalAndTest() - Open modal and run tests automatically');
console.log('• debugModalState() - Debug modal and function availability');
console.log('\n💡 QUICK START:');
console.log('1. Make sure you\'re logged in');
console.log('2. Run: openModalAndTest()');
console.log('3. Watch the console and the modal\'s conflict detection panel');

// Auto-run debug to show current state
debugModalState();