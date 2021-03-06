<template>

    <Row>
        <Col span="8" :offset="8">

            <Button type="default" class="mt-5 mb-2" @click="navigateToProjects()">
                <Icon type="md-arrow-back" class="mr-1" :size="20" />
                <span>Projects</span>
            </Button>

            <Card class="pt-2">

                <!-- Heading -->
                <Divider orientation="left" class="font-weight-bold">Create Project</Divider>

                <!-- Clone Project Alert -->
                <Alert v-if="cloneProject && !isSearching" type="info" show-icon>Cloning "{{ cloneProject.name }}"</Alert>

                <template v-else>

                    <!-- If we are loading, Show Loader -->
                    <Loader v-if="isSearching && !isLoading" class="mt-2 mb-2">Searching for project to clone...</Loader>

                </template>

                <!-- Error Message Alert -->
                <Alert v-if="serverErrorMessage  && !isLoading" type="warning">{{ serverErrorMessage }}</Alert>
                <Alert v-if="serverGeneralError  && !isLoading" type="warning">{{ serverGeneralError }}</Alert>

                <Form ref="projectForm" :model="projectForm" :rules="projectFormRules" @submit.native.prevent="handleSubmit()">

                    <!-- Enter Name -->
                    <FormItem prop="name" :error="serverNameError">
                        <Input type="text" v-model="projectForm.name" placeholder="Name" :disabled="isLoading"
                                maxlength="50" show-word-limit @keyup.enter.native="handleSubmit()">
                        </Input>
                    </FormItem>

                    <!-- Enter Description -->
                    <FormItem prop="description" :error="serverDescriptionError">
                        <Input type="textarea" v-model="projectForm.description" placeholder="Description" :disabled="isLoading"
                                maxlength="500" show-word-limit @keyup.enter.native="handleSubmit()">
                        </Input>
                    </FormItem>

                    <!-- Enter Dedicated Short Code -->
                    <FormItem prop="dedicated_short_code" :error="serverDedicatedShortCodeError">
                        <div class="d-flex">
                            <span :style="{ width: '200px' }" class="font-weight-bold">Dedicated Code: </span>
                            <Input type="text" v-model.number="projectForm.dedicated_short_code" placeholder="180"
                                   :disabled="isLoading" @keyup.enter.native="handleSubmit()">
                                <span slot="prepend">*</span>
                                <span slot="append">#</span>
                            </Input>
                        </div>
                    </FormItem>

                    <!-- Select Shared Short Code -->
                    <FormItem prop="shared_short_code" :error="serverSharedShortCodeError">
                        <div class="d-flex">
                            <span :style="{ width: '235px' }" class="font-weight-bold">Shared Code: </span>
                            <Select v-model="projectForm.shared_short_code" :disabled="isLoading" class="w-100 mr-2">
                                <Option v-for="(shared_short_code, index) in shared_short_codes" :value="shared_short_code" :key="index">
                                    {{ shared_short_code }}
                                </Option>
                            </Select>
                            <!-- Refresh Button -->
                            <Poptip trigger="hover" content="Refresh the shared short codes" word-wrap width="300"
                                    :style="{ marginTop: '-2px' }">
                                <Button class="p-1">
                                    <Icon type="ios-refresh" :size="20" />
                                </Button>
                            </Poptip>
                        </div>
                    </FormItem>

                    <span class="d-inline-block mr-2">
                        <span class="font-weight-bold">Highlighter</span>:
                        <ColorPicker v-model="projectForm.hex_color" recommend></ColorPicker>
                    </span>

                    <!-- Create Button -->
                    <FormItem v-if="!isLoading">
                        <Button type="primary" class="float-right" :disabled="isSearching || isLoading" @click="handleSubmit()">Create Project</Button>
                    </FormItem>

                    <!-- If we are loading, Show Loader -->
                    <Loader v-show="isLoading" class="mt-2">Creating project...</Loader>

                </Form>

            </Card>
        </Col>
    </Row>

</template>
<script>

    import Loader from './../../../components/_common/loaders/default.vue';

    export default {
        components: { Loader },
        data () {

            return {
                cloneProject: null,
                isSearching: false,
                isLoading: false,
                projectForm: {
                    name: '',
                    description: '',
                    hex_color: '2D8CF0',
                    shared_short_code: '',
                    clone_project_id: null,
                    dedicated_short_code: '',
                },
                projectFormRules: {
                    name: [
                        { required: true, message: 'Please enter your project name', trigger: 'blur' },
                        { min: 3, message: 'Project name is too short', trigger: 'change' },
                        { max: 50, message: 'Project name is too long', trigger: 'change' }
                    ],
                    description: [
                        { max: 500, message: 'Project description is too long', trigger: 'change' }
                    ],
                    dedicated_short_code: [
                        { type: 'number', message: 'The dedicated short code must be a number', trigger: 'blur' }
                    ],
                    shared_short_code: [
                        { required: true, message: 'Please select a shared short code', trigger: 'blur' }
                    ],
                },
                serverErrors: [],
                shared_short_codes: ['*321#', '*432#', '*543#'],
                serverErrorMessage: '',
                user: auth.getUser()
            }
        },
        computed: {
            serverGeneralError(){
                return (this.serverErrors || {}).general;
            },
            serverNameError(){
                return (this.serverErrors || {}).name;
            },
            serverDescriptionError(){
                return (this.serverErrors || {}).description;
            },
            serverDedicatedShortCodeError(){
                return (this.serverErrors || {}).dedicated_short_code;
            },
            serverSharedShortCodeError(){
                return (this.serverErrors || {}).shared_short_code;
            },
            cloneProjectUrl(){
                if( this.$route.query.project_url ){
                    return decodeURIComponent(this.$route.query.project_url);
                }
            },
        },
        methods: {
            navigateToProjects(){

                //  Redirect the user to the projects page
                this.$router.push({ name: 'show-projects' });

            },
            handleSubmit(){

                //  Reset the server errors
                this.resetErrors();

                //  Validate the form
                this.$refs['projectForm'].validate((valid) =>
                {
                    //  If the validation passed
                    if (valid) {

                        //  Attempt to create the project
                        this.attemptProjectCreation();

                    //  If the validation failed
                    } else {
                        this.$Message.warning({
                            content: 'Sorry, you cannot create your project yet',
                            duration: 6
                        });
                    }
                })
            },
            fetchProjectToClone() {

                //  If we have the project url
                if( this.cloneProjectUrl ){

                    //  Hold constant reference to the current Vue instance
                    const self = this;

                    //  Start loader
                    self.isSearching = true;

                    //  Use the api call() function, refer to api.js
                    api.call('get', this.cloneProjectUrl)
                        .then(({data}) => {

                            //  Console log the data returned
                            console.log(data);

                            //  Get the project
                            self.cloneProject = data || null;

                            //  Update the clone project id
                            self.$set(self.projectForm, 'clone_project_id', (data || {}).id);

                            //  Stop loader
                            self.isSearching = false;

                        })
                        .catch(response => {

                            //  Log the responce
                            console.error(response);

                            //  Stop loader
                            self.isSearching = false;

                        });
                }
            },
            attemptProjectCreation(){

                //  Hold constant reference to the current Vue instance
                const self = this;

                //  Start loader
                self.isLoading = true;

                /**  Make an Api call to create the project. We include the
                 *   project details required for a new project creation.
                 */
                let data = {
                    postData: this.projectForm
                };

                /**  Note "api_home" is defined within the auth.js file.
                 *   It holds reference to common links for ease of
                 *   access.
                 */
                let url = api_home['_links']['sce:projects'].href

                return api.call('post', url, data)
                    .then(({data}) => {

                        //  Stop loader
                        self.isLoading = false;

                        //  Reset the form
                        self.resetProjectForm();

                        //  Project created success message
                        self.$Message.success({
                            content: 'Your project has been created!',
                            duration: 6
                        });

                        //  Redirect the user to the projects page
                        this.$router.push({ name: 'show-projects' });

                    }).catch((response) => {

                        console.log(response);

                        //  Stop loader
                        self.isLoading = false;

                        //  Get the error response data
                        let data = (response || {}).data;

                        //  Get the response errors
                        var errors = (data || {}).errors;

                        //  Set the general error message
                        self.serverErrorMessage = (data || {}).message;

                        /** 422: Validation failed. Incorrect credentials
                         */
                        if((response || {}).status === 422){

                            //  If we have errors
                            if(_.size(errors)){

                                //  Set the server errors
                                self.serverErrors = errors;

                                //  Foreach error
                                for (var i = 0; i < _.size(errors); i++) {
                                    //  Get the error key e.g 'email', 'password'
                                    var prop = Object.keys(errors)[i];
                                    //  Get the error value e.g 'These credentials do not match our records.'
                                    var value = Object.values(errors)[i][0];

                                    //  Dynamically update the serverErrors for View UI to display the error on the appropriate form item
                                    self.serverErrors[prop] = value;
                                }

                            }

                        }

                });
            },
            resetErrors(){
                this.serverErrorMessage = '';
                this.serverErrors = [];
            },
            resetProjectForm(){
                this.resetErrors();
                this.$refs['projectForm'].resetFields();
            }
        },
        created() {
            this.fetchProjectToClone();
        }
    }
</script>
