<?php
session_start();
require_once '../../dbRelated/operation.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$teacher_id = $_SESSION['user_id'];
$myClasses = $db->getTeacherClasses($teacher_id);
$inventory = $db->getInventoryShop(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Activity Wizard | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        [x-cloak] { display: none !important; }
        .shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen font-sans text-slate-600">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-6 md:p-12" x-data="activityWizard()">
                
                <div class="max-w-6xl mx-auto bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100 relative">
                    
                    <div class="bg-[#0f172a] p-8 text-white flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-black italic tracking-tighter">Create <span class="text-blue-500">Activity.</span></h1>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-2">Step <span x-text="step"></span>: <span x-text="stepTitle"></span></p>
                        </div>
                        <div class="flex gap-2">
                            <template x-for="i in 3"><div class="h-2 w-8 rounded-full transition-all" :class="step >= i ? 'bg-blue-600' : 'bg-white/10'"></div></template>
                        </div>
                    </div>

                    <form id="wizardForm" class="p-8 md:p-12 relative min-h-[600px]" @submit.prevent="handleFinish">
                        
                        <div x-show="step === 1" x-transition.opacity>
                            <h2 class="text-xl font-bold text-slate-800 mb-6 border-b pb-4">1. Activity Resources</h2>
                            <div class="grid lg:grid-cols-2 gap-8">
                                <div class="space-y-5">
                                    <input type="text" name="title" required class="w-full bg-slate-50 p-4 rounded-2xl font-bold text-slate-700 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Activity Title">
                                    <textarea name="description" rows="3" class="w-full bg-slate-50 p-4 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500" placeholder="Instructions..."></textarea>
                                    <input type="datetime-local" name="deadline" required class="w-full bg-slate-50 p-4 rounded-2xl font-bold text-sm outline-none">
                                    
                                    <div class="bg-blue-50 p-6 rounded-2xl border-2 border-dashed border-blue-200 relative text-center">
                                        <label class="cursor-pointer block">
                                            <span class="text-3xl block mb-2">📄</span>
                                            <span class="font-bold text-blue-700 text-sm">Upload PDF Manual</span>
                                            <input type="file" name="manual" accept="application/pdf" class="hidden" @change="previewPDF">
                                        </label>
                                        <p x-show="fileName" class="text-xs font-bold text-slate-500 mt-2" x-text="'Selected: ' + fileName"></p>
                                    </div>
                                </div>
                                <div class="bg-slate-900 rounded-2xl h-[450px] overflow-hidden relative">
                                    <iframe x-show="pdfUrl" :src="pdfUrl" class="w-full h-full bg-white"></iframe>
                                    <div x-show="!pdfUrl" class="absolute inset-0 flex items-center justify-center text-slate-500 text-xs font-bold uppercase">Preview Area</div>
                                </div>
                            </div>
                        </div>

                        <div x-show="step === 2" x-transition.opacity style="display: none;">
                            <h2 class="text-xl font-bold text-slate-800 mb-6 border-b pb-4">2. Logistics & Classes</h2>
                            <div class="grid lg:grid-cols-2 gap-8">
                                <div>
                                    <div class="flex justify-between mb-4"><h3 class="font-bold text-xs uppercase text-slate-400">Inventory</h3><button type="button" @click="openShop = true" class="text-blue-600 text-xs font-bold">+ Add Items</button></div>
                                    <div class="bg-slate-50 p-4 rounded-2xl min-h-[200px] space-y-2">
                                        <template x-for="(item, i) in selectedItems">
                                            <div class="flex items-center gap-3 bg-white p-3 rounded-xl shadow-sm">
                                                <span class="text-xs font-bold text-slate-700 flex-1" x-text="item.name"></span>
                                                <input type="hidden" name="items[]" :value="item.id">
                                                <input type="number" name="qtys[]" value="1" class="w-12 bg-slate-100 text-center text-xs font-bold p-1 rounded">
                                                <button type="button" @click="removeItem(i)" class="text-red-400 font-bold">&times;</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="font-bold text-xs uppercase text-slate-400 mb-4">Assign Classes</h3>
                                    <div class="space-y-3">
                                        <?php foreach ($myClasses as $class): ?>
                                            <label class="flex items-center gap-4 p-4 bg-white border border-slate-100 rounded-2xl cursor-pointer hover:border-blue-400 transition-all">
                                                <input type="checkbox" name="target_classes[]" value="<?= $class['ClassID'] ?>" class="w-5 h-5 rounded text-blue-600 focus:ring-0">
                                                <div>
                                                    <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($class['Class_Name']) ?></p>
                                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($class['Section']) ?></p>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div x-show="step === 3" x-transition.opacity style="display: none;">
                            <h2 class="text-xl font-bold text-slate-800 mb-6 border-b pb-4">3. Workflow Logic</h2>
                            <div class="grid md:grid-cols-2 gap-8">
                                <div class="bg-indigo-50 p-6 rounded-2xl">
                                    <h3 class="text-indigo-900 font-bold mb-4">Mode</h3>
                                    <div class="flex gap-2 mb-4">
                                        <button type="button" @click="mode='Individual'" :class="mode=='Individual'?'bg-indigo-600 text-white':'bg-white text-indigo-900'" class="flex-1 py-3 rounded-xl font-bold text-xs uppercase">Individual</button>
                                        <button type="button" @click="mode='Group'" :class="mode=='Group'?'bg-indigo-600 text-white':'bg-white text-indigo-900'" class="flex-1 py-3 rounded-xl font-bold text-xs uppercase">Group</button>
                                    </div>
                                    <input type="hidden" name="activity_type" x-model="mode">
                                    
                                    <div x-show="mode === 'Group'">
                                        <label class="block text-xs font-bold uppercase text-indigo-400 mb-2">Strategy</label>
                                        <select name="grouping_mode" x-model="grouping" class="w-full p-3 rounded-xl text-sm font-bold mb-4 outline-none">
                                            <option value="Auto">Smart Auto-Assign</option>
                                            <option value="Manual">Teacher Selects (Manual)</option>
                                            <option value="Student">Student Choice</option>
                                        </select>
                                        
                                        <label class="block text-xs font-bold uppercase text-indigo-400 mb-2">
                                            <span x-text="grouping === 'Manual' ? 'Target Members Per Group' : 'Group Limit'"></span>
                                        </label>
                                        <input type="number" name="group_limit" x-model="group_limit" value="4" min="1" class="w-full p-3 rounded-xl text-sm font-bold border border-indigo-100">
                                        
                                        <p class="text-[10px] text-indigo-400 mt-2 italic" x-show="grouping === 'Manual'">
                                            * Configure specific members in the next step.
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-emerald-50 p-6 rounded-2xl">
                                    <h3 class="text-emerald-900 font-bold mb-4">Submission</h3>
                                    <label class="flex items-center gap-3 p-3 bg-white rounded-xl mb-2 cursor-pointer">
                                        <input type="radio" name="submission_mode" value="File" checked class="text-emerald-600">
                                        <span class="text-sm font-bold text-emerald-800">File Upload</span>
                                    </label>
                                    <label class="flex items-center gap-3 p-3 bg-white rounded-xl cursor-pointer">
                                        <input type="radio" name="submission_mode" value="Builder" class="text-emerald-600">
                                        <span class="text-sm font-bold text-emerald-800">Smart-Lock Builder</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="absolute bottom-0 left-0 w-full p-8 border-t border-slate-100 flex justify-between bg-white rounded-b-[2.5rem]">
                            <button type="button" x-show="step > 1" @click="step--" class="text-slate-400 font-bold hover:text-slate-600">Back</button>
                            <div class="ml-auto">
                                <button type="button" x-show="step < 3" @click="step++" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-700">Next</button>
                                <button type="submit" x-show="step === 3" class="bg-[#0f172a] text-white px-10 py-3 rounded-xl font-bold hover:bg-green-600 shadow-xl">
                                    <span x-text="grouping === 'Manual' && mode === 'Group' ? 'Configure Groups & Publish' : 'Publish Activity'"></span>
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

                <div x-show="openShop" class="fixed inset-0 z-[60] bg-slate-900/90 backdrop-blur-sm flex items-center justify-center p-6" x-cloak>
                    <div class="bg-white rounded-3xl w-full max-w-4xl h-[80vh] flex flex-col p-6 shadow-2xl">
                        <div class="flex justify-between mb-4">
                            <h2 class="text-2xl font-black text-slate-800">Inventory</h2>
                            <button @click="openShop = false" class="text-red-500 font-bold">Close</button>
                        </div>
                        <input type="text" x-model="search" placeholder="Search..." class="w-full bg-slate-100 p-3 rounded-xl font-bold mb-4 outline-none">
                        <div class="flex-1 overflow-y-auto shop-grid content-start">
                            <?php foreach($inventory as $item): ?>
                                <div class="bg-slate-50 p-4 rounded-xl cursor-pointer hover:bg-blue-50 border border-slate-100 hover:border-blue-300 transition-all text-center"
                                     x-show="'<?= strtolower($item['Item_Name']) ?>'.includes(search.toLowerCase())"
                                     @click="addItem('<?= $item['ItemID'] ?>', '<?= addslashes(htmlspecialchars($item['Item_Name'])) ?>')">
                                    <img src="../../assets/img/items/<?= $item['ItemID'] ?>.png" class="h-16 mx-auto mb-2 opacity-80" onerror="this.src='../../assets/img/placeholder.png'">
                                    <p class="text-xs font-bold text-slate-700 line-clamp-2"><?= htmlspecialchars($item['Item_Name']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div x-show="openGroupingBoard" class="fixed inset-0 z-[70] bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4 md:p-8" x-cloak>
                    
                    <div class="w-full max-w-7xl h-[90vh] bg-[#0f172a] rounded-[2.5rem] shadow-2xl flex flex-col overflow-hidden border border-slate-700 relative animate-reveal active">
                        
                        <button @click="openGroupingBoard = false" class="absolute top-6 right-6 text-slate-500 hover:text-white transition-colors z-50">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <div class="p-8 bg-slate-900 border-b border-slate-800 flex justify-between items-center text-white">
                            <div>
                                <h2 class="text-3xl font-black italic">Grouping <span class="text-blue-500">Board.</span></h2>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">
                                    Target Size: <span class="text-blue-400" x-text="group_limit"></span> | 
                                    Class Size: <span class="text-green-400" x-text="totalClassSize"></span>
                                </p>
                            </div>
                            <div class="flex gap-4 mr-8">
                                <button @click="openGroupingBoard = false" class="text-slate-400 hover:text-white font-bold text-xs uppercase">Cancel</button>
                                <button @click="finalSubmit" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-green-500 transition-colors shadow-[0_0_20px_rgba(37,99,235,0.5)]">
                                    Confirm & Publish
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 flex overflow-hidden">
                            
                            <div class="w-1/4 bg-slate-900 border-r border-slate-800 p-6 flex flex-col">
                                <h3 class="text-white font-bold text-xs uppercase tracking-widest mb-4">Class Roster <span class="bg-slate-800 px-2 rounded text-slate-400" x-text="roster.length"></span></h3>
                                <div class="flex-1 overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                                    <template x-for="stu in roster" :key="stu.MasterID">
                                        <div class="bg-slate-800 p-3 rounded-lg border border-slate-700 hover:border-blue-500 cursor-pointer group transition-all"
                                             :class="selectedStudent === stu ? 'border-blue-500 bg-slate-800/80' : ''"
                                             @click="selectStudent(stu)">
                                            <p class="text-xs font-bold text-slate-200 group-hover:text-white" x-text="stu.Full_Name"></p>
                                            <p class="text-[9px] text-slate-500 font-bold uppercase" x-text="stu.Class_Name"></p>
                                        </div>
                                    </template>
                                    <div x-show="roster.length === 0" class="text-slate-600 text-xs font-bold text-center mt-10">All assigned!</div>
                                </div>
                            </div>

                            <div class="flex-1 bg-slate-950 p-8 overflow-y-auto">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pb-20">
                                    
                                    <button @click="addGroup" class="border-2 border-dashed border-slate-800 rounded-2xl p-6 flex items-center justify-center text-slate-600 font-black uppercase text-xs tracking-widest hover:text-blue-500 hover:border-blue-500 transition-all h-32">
                                        + Add Extra Group
                                    </button>

                                    <template x-for="(group, gIndex) in manualGroups" :key="gIndex">
                                        <div class="bg-slate-900 rounded-2xl p-4 border shadow-xl flex flex-col h-full min-h-[200px] transition-colors"
                                             :class="group.members.length > group_limit ? 'border-red-500/50' : 'border-slate-800'">
                                            
                                            <div class="flex justify-between items-center mb-4 border-b border-slate-800 pb-2">
                                                <input type="text" x-model="group.name" class="bg-transparent text-white font-bold text-sm outline-none w-full" placeholder="Group Name">
                                                
                                                <span class="text-[10px] font-black mr-2" 
                                                      :class="group.members.length > group_limit ? 'text-red-500 animate-pulse' : (group.members.length == group_limit ? 'text-green-500' : 'text-slate-500')">
                                                    (<span x-text="group.members.length"></span>/<span x-text="group_limit"></span>)
                                                </span>

                                                <button @click="removeGroup(gIndex)" class="text-slate-600 hover:text-red-500 font-bold">&times;</button>
                                            </div>

                                            <div class="flex-1 space-y-2">
                                                <template x-for="(member, mIndex) in group.members" :key="member.MasterID">
                                                    <div class="bg-slate-800 p-2 rounded flex items-center gap-2 group/mem border border-transparent hover:border-slate-700">
                                                        <button @click="toggleLeader(gIndex, mIndex)" 
                                                                class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] transition-colors" 
                                                                :class="member.isLeader ? 'bg-yellow-500 text-black' : 'bg-slate-700 text-slate-500 hover:bg-slate-600'"
                                                                title="Toggle Leader">
                                                            👑
                                                        </button>
                                                        <span class="text-xs font-bold text-slate-300 flex-1 truncate" x-text="member.Full_Name"></span>
                                                        <button @click="returnToRoster(gIndex, mIndex)" class="text-slate-600 hover:text-red-400 font-bold">&times;</button>
                                                    </div>
                                                </template>
                                                
                                                <div x-show="selectedStudent" 
                                                     @click="placeStudent(gIndex)"
                                                     class="border border-dashed border-blue-500/50 bg-blue-500/10 p-3 rounded text-center text-[10px] font-bold text-blue-400 cursor-pointer hover:bg-blue-500/20 animate-pulse transition-all">
                                                    Click to add <span x-text="selectedStudent?.Full_Name"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('activityWizard', () => ({
                step: 1,
                openShop: false,
                openGroupingBoard: false,
                search: '',
                selectedItems: [],
                mode: 'Individual',
                grouping: 'Auto',
                group_limit: 4,
                fileName: '',
                pdfUrl: null,
                
                roster: [],
                manualGroups: [], 
                selectedStudent: null,
                totalClassSize: 0,

                get stepTitle() { return ['Resources', 'Logistics', 'Settings'][this.step - 1]; },

                previewPDF(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.fileName = file.name;
                        this.pdfUrl = URL.createObjectURL(file);
                    }
                },

                addItem(id, name) {
                    if (!this.selectedItems.some(i => i.id === id)) this.selectedItems.push({ id, name });
                    this.openShop = false;
                },
                removeItem(i) { this.selectedItems.splice(i, 1); },

                handleFinish() {
                    if (this.mode === 'Group' && this.grouping === 'Manual') {
                        this.fetchRosterAndOpenBoard();
                    } else {
                        this.submitForm();
                    }
                },

             fetchRosterAndOpenBoard() {
    // 1. Get checked classes
    const checkboxes = document.querySelectorAll('input[name="target_classes[]"]:checked');
    const classIDs = Array.from(checkboxes).map(cb => cb.value);

    // 2. Validate selection
    if (classIDs.length === 0) {
        alert("⚠️ Please go back to Step 2 and select at least one class.");
        this.step = 2;
        return;
    }

    // 3. Prepare Data
    const fd = new FormData();
    fd.append('classes', classIDs.join(','));

    // 4. Visual Feedback (Optional: Change button text if you bound it)
    console.log("Requesting roster for classes:", classIDs);

    // 5. Fetch with detailed error handling
    fetch('../../dbRelated/get_roster.php', { 
        method: 'POST', 
        body: fd 
    })
    .then(async response => {
        const text = await response.text(); // Get raw text first to debug PHP errors
        
        if (!response.ok) {
            throw new Error(`Server Error (${response.status}): ${text}`);
        }

        try {
            return JSON.parse(text); // Try parsing JSON
        } catch (e) {
            console.error("Raw Server Response:", text);
            throw new Error("Server returned invalid JSON. Check console for details.");
        }
    })
    .then(data => {
        if (data.error) {
            throw new Error(data.error); // Handle PHP logic errors
        }

        console.log("Roster loaded:", data);
        this.roster = data;
        this.totalClassSize = data.length;
        this.manualGroups = [];

        // Auto-generate empty groups based on limit
        if (this.group_limit > 0 && this.totalClassSize > 0) {
            const numGroups = Math.ceil(this.totalClassSize / this.group_limit);
            for (let i = 0; i < numGroups; i++) {
                this.manualGroups.push({
                    name: 'Group ' + (i + 1),
                    members: []
                });
            }
        }

        // OPEN THE BOARD
        this.openGroupingBoard = true; 
    })
    .catch(err => {
        console.error(err);
        alert("❌ Error fetching students:\n" + err.message);
    });
},

                addGroup() {
                    this.manualGroups.push({ 
                        name: 'Group ' + (this.manualGroups.length + 1), 
                        members: [] 
                    });
                },

                removeGroup(index) {
                    const members = this.manualGroups[index].members;
                    members.forEach(m => { m.isLeader = false; this.roster.push(m); });
                    this.manualGroups.splice(index, 1);
                },

                selectStudent(stu) {
                    this.selectedStudent = (this.selectedStudent === stu) ? null : stu;
                },

                placeStudent(groupIndex) {
    if (!this.selectedStudent) return;
    
    const group = this.manualGroups[groupIndex];
    const limit = parseInt(this.group_limit); // Ensure it is a number
    
    // STRICT CHECK: No exceeding the limit
    if (group.members.length >= limit) {
        alert(`⛔ This group is full! (Limit: ${limit})\n\nTip: Click "+ Add Extra Group" if you need more space.`);
        return;
    }

    // Add student
    const stu = this.selectedStudent;
    stu.isLeader = false; 
    
    this.manualGroups[groupIndex].members.push(stu);
    
    // Remove from roster
    this.roster = this.roster.filter(s => s.MasterID !== stu.MasterID);
    this.selectedStudent = null;
},

                returnToRoster(gIndex, mIndex) {
                    const stu = this.manualGroups[gIndex].members[mIndex];
                    stu.isLeader = false;
                    this.roster.push(stu);
                    this.manualGroups[gIndex].members.splice(mIndex, 1);
                },

                toggleLeader(gIndex, mIndex) {
                    const group = this.manualGroups[gIndex];
                    const clickedMember = group.members[mIndex];
                    const wasLeader = clickedMember.isLeader;

                    group.members.forEach(m => m.isLeader = false); 
                    
                    if (!wasLeader) {
                        clickedMember.isLeader = true;
                    }
                },

finalSubmit() {
    // 1. CHECK: Are there students left in the roster?
    if (this.roster.length > 0) {
        alert(`⚠️ You have ${this.roster.length} unassigned student(s)!\n\nEvery student must be assigned to a group before you can publish.`);
        return;
    }

    // 2. CHECK: Are there empty groups?
    if (this.manualGroups.some(g => g.members.length === 0)) {
        if(!confirm("⚠️ You have empty groups created. These will be ignored. Continue?")) return;
        // Optional: Filter out empty groups automatically
        this.manualGroups = this.manualGroups.filter(g => g.members.length > 0);
    }

    // 3. CHECK: Does every group have a leader?
    const leaderlessGroups = this.manualGroups.filter(g => 
        g.members.length > 0 && !g.members.some(m => m.isLeader)
    );
    
    if (leaderlessGroups.length > 0) {
        const names = leaderlessGroups.map(g => g.name).join(', ');
        alert(`⚠️ Missing Leaders!\n\nPlease assign a leader (👑) for:\n• ${names}`);
        return;
    }

    // 4. All Good -> Submit
    this.submitForm();
},
                submitForm() {
                    const form = document.getElementById('wizardForm');
                    const formData = new FormData(form);
                    
                    if (this.mode === 'Group' && this.grouping === 'Manual') {
                        formData.append('manual_groups_json', JSON.stringify(this.manualGroups));
                    }
                    
                    fetch('../../dashboard/save_activity.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => {
                            if(d.status === 'success') window.location.href = d.redirect;
                            else alert(d.message);
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Server error occurred.");
                        });
                }
            }));
        });
    </script>
</body>
</html>