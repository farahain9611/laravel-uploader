<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Uploader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100">
    <div id="app" class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">CSV Uploader</h1>
            
            <!-- Upload Form -->
            <div class="max-w-2xl mx-auto mb-8">
                <form @submit.prevent="uploadFile">
                    <div 
                        class="flex items-center border border-black rounded p-4 w-full"
                        :class="{'bg-blue-50 border-blue-500': isDragging}"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleFileDrop"
                    >
                        <label 
                            for="file-upload"
                            class="flex-1 cursor-pointer select-none text-gray-700"
                            @click="() => { $refs.fileInput.click(); }"
                        >
                            <span v-if="!file">Select file/Drag and drop</span>
                            <span v-else>@{{ file.name }}</span>
                            <input id="file-upload" type="file" accept=".csv" ref="fileInput" @change="handleFileSelect" class="hidden">
                        </label>
                        <button type="submit" 
                                class="ml-4 px-4 py-2 border border-blue-600 rounded bg-blue-600 text-white hover:bg-blue-700 focus:outline-none"
                                :disabled="uploading || !file">
                            <span v-if="uploading">Uploading...</span>
                            <span v-else>Upload File</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">@{{ error }}</span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3" @click="error = null">
                    <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </span>
            </div>

            <!-- Polling Status -->
            <div v-if="pollingError" 
                 :class="{
                     'px-4 py-3 rounded relative mb-4': true,
                     'bg-yellow-100 border border-yellow-400 text-yellow-700': pollingError.type === 'warning',
                     'bg-red-100 border border-red-400 text-red-700': pollingError.type === 'error'
                 }" 
                 role="alert">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg v-if="pollingError.type === 'warning'" class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <svg v-else class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span class="block sm:inline">@{{ pollingError.message }}</span>
                    </div>
                    <div class="flex items-center">
                        <button v-if="pollingError.action === 'refresh'" 
                                @click="startPolling" 
                                class="text-sm font-medium underline hover:no-underline focus:outline-none">
                            Refresh
                        </button>
                        <button v-if="pollingError.action === 'retry'" 
                                @click="fetchUploads" 
                                :disabled="isRetrying"
                                class="text-sm font-medium underline hover:no-underline focus:outline-none">
                            Retry Now
                        </button>
                        <button @click="pollingError = null" 
                                class="ml-4 focus:outline-none">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upload History -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Upload History</h2>
                    <span v-if="isPolling" class="text-sm text-gray-500">Last updated: @{{ lastUpdated }}</span>
                </div>
                <div class="space-y-4">
                    <div v-for="upload in uploads" :key="upload.id" class="border rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium">@{{ upload.filename }}</p>
                                <p class="text-sm text-gray-500">@{{ formatDate(upload.created_at) }}</p>
                            </div>
                            <div>
                                <span :class="{
                                    'px-2 py-1 rounded-full text-xs font-medium': true,
                                    'bg-yellow-100 text-yellow-800': upload.status === 'pending',
                                    'bg-blue-100 text-blue-800': upload.status === 'processing',
                                    'bg-green-100 text-green-800': upload.status === 'completed',
                                    'bg-red-100 text-red-800': upload.status === 'failed'
                                }">
                                    @{{ upload.status }}
                                </span>
                            </div>
                        </div>
                        <p v-if="upload.error_message" class="mt-2 text-sm text-red-600">
                            @{{ upload.error_message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue

        createApp({
            data() {
                return {
                    file: null,
                    uploading: false,
                    uploads: @json($uploads),
                    error: null,
                    pollingError: null,
                    pollInterval: null,
                    isPolling: false,
                    lastUpdated: null,
                    retryCount: 0,
                    maxRetries: 3,
                    isRetrying: false,
                    lastPollTime: null,
                    pollTimeout: null,
                    isDragging: false
                }
            },
            mounted() {
                // Start polling
                this.startPolling();

                // Subscribe to Pusher channel
                const pusher = new Pusher('{{ config('broadcasting.connections.pusher.key') }}', {
                    cluster: '{{ config('broadcasting.connections.pusher.options.cluster') }}'
                });

                const channel = pusher.subscribe('csv-uploads');
                channel.bind('CsvUploadStatusUpdated', (data) => {
                    const index = this.uploads.findIndex(u => u.id === data.upload.id);
                    if (index !== -1) {
                        this.uploads[index] = data.upload;
                    }
                });
            },
            beforeUnmount() {
                // Clean up polling when component is destroyed
                this.stopPolling();
            },
            methods: {
                startPolling() {
                    this.isPolling = true;
                    this.retryCount = 0;
                    this.pollingError = null;
                    this.lastPollTime = new Date();
                    // Poll every 1 second
                    this.pollInterval = setInterval(this.fetchUploads, 1000);
                    // Initial fetch
                    this.fetchUploads();
                },
                stopPolling() {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                        this.isPolling = false;
                    }
                    if (this.pollTimeout) {
                        clearTimeout(this.pollTimeout);
                    }
                },
                async fetchUploads() {
                    try {
                        // Set a timeout for the request
                        const timeoutPromise = new Promise((_, reject) => {
                            this.pollTimeout = setTimeout(() => {
                                reject(new Error('Request timeout'));
                            }, 10000); // 10 second timeout
                        });

                        const response = await Promise.race([
                            axios.get('/status'),
                            timeoutPromise
                        ]);

                        clearTimeout(this.pollTimeout);
                        this.uploads = response.data;
                        this.lastUpdated = new Date().toLocaleTimeString();
                        this.pollingError = null;
                        this.retryCount = 0;
                        this.isRetrying = false;
                        this.lastPollTime = new Date();
                    } catch (error) {
                        console.error('Failed to fetch uploads:', error);
                        this.retryCount++;
                        
                        if (this.retryCount >= this.maxRetries) {
                            this.pollingError = {
                                message: `Failed to fetch updates after ${this.maxRetries} attempts.`,
                                type: 'error',
                                action: 'refresh'
                            };
                            this.stopPolling();
                        } else {
                            this.isRetrying = true;
                            this.pollingError = {
                                message: `Connection lost. Retrying... (${this.retryCount}/${this.maxRetries})`,
                                type: 'warning',
                                action: 'retry'
                            };
                            
                            // Exponential backoff for retries
                            const backoffTime = Math.min(1000 * Math.pow(2, this.retryCount), 30000);
                            setTimeout(() => {
                                this.isRetrying = false;
                                this.fetchUploads();
                            }, backoffTime);
                        }
                    }
                },
                handleFileSelect(event) {
                    this.file = event.target.files[0];
                    this.error = null;
                },
                handleFileDrop(event) {
                    this.isDragging = false;
                    const droppedFile = event.dataTransfer.files[0];
                    if (droppedFile && droppedFile.type === 'text/csv') {
                        this.file = droppedFile;
                        this.error = null;
                    } else {
                        this.error = 'Please drop a valid CSV file';
                    }
                },
                removeFile() {
                    this.file = null;
                    this.$refs.fileInput.value = '';
                    this.error = null;
                },
                async uploadFile() {
                    if (!this.file) {
                        this.error = 'Please select a file first';
                        return;
                    }

                    this.uploading = true;
                    this.error = null;
                    const formData = new FormData();
                    formData.append('csv_file', this.file);

                    try {
                        const response = await axios.post('/upload', formData, {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        });
                        
                        if (response.data.error) {
                            this.error = response.data.error;
                        } else {
                            this.uploads.unshift(response.data.upload);
                            this.file = null;
                            this.$refs.fileInput.value = '';
                        }
                    } catch (error) {
                        console.error('Upload failed:', error);
                        if (error.response?.data?.message) {
                            this.error = error.response.data.message;
                        } else if (error.response?.data?.error) {
                            this.error = error.response.data.error;
                        } else {
                            this.error = 'Upload failed. Please try again.';
                        }
                    } finally {
                        this.uploading = false;
                    }
                },
                formatDate(date) {
                    return new Date(date).toLocaleString();
                }
            }
        }).mount('#app')
    </script>
</body>
</html> 