<template>
    <default-field :field="field" :errors="errors">
        <template slot="field">
            <div v-if="hasValue" class="mb-6">
                <template v-if="shouldShowLoader">
                    <ImageLoader
                        :src="imageUrl"
                        :maxWidth="maxWidth"
                        @missing="value => (missing = value)"
                    />
                </template>

                <template v-if="field.value && !imageUrl">
                    <card
                        class="flex item-center relative border border-lg border-50 overflow-hidden p-4"
                    >
                        <span class="truncate mr-3"> {{ field.value }} </span>

                        <DeleteButton
                            :dusk="field.attribute + '-internal-delete-link'"
                            class="ml-auto"
                            v-if="shouldShowRemoveButton"
                            @click="confirmRemoval"
                        />
                    </card>
                </template>

                <p v-if="imageUrl" class="mt-3 flex items-center text-sm">
                    <DeleteButton
                        :dusk="field.attribute + '-delete-link'"
                        v-if="shouldShowRemoveButton"
                        @click="confirmRemoval"
                    >
                        <span class="class ml-2 mt-1"> {{ __('Delete') }} </span>
                    </DeleteButton>
                </p>

                <portal to="modals">
                    <transition name="fade">
                        <confirm-upload-removal-modal
                            v-if="removeModalOpen"
                            @confirm="removeFile"
                            @close="closeRemoveModal"
                        />
                    </transition>
                </portal>
            </div>

            <span class="form-file mr-4" :class="{ 'opacity-75': isReadonly }">
                <input
                    ref="fileField"
                    :dusk="field.attribute"
                    class="form-file-input select-none"
                    type="file"
                    :id="idAttr"
                    name="name"
                    @change="fileChange"
                    :disabled="isReadonly"
                />
                <label
                    :for="labelFor"
                    class="form-file-btn btn btn-default btn-primary select-none"
                >
                    {{ __('Choose File') }}
                </label>
            </span>

            <span class="text-gray-50 select-none"> {{ currentLabel }} </span>

            <p v-if="hasError" class="text-xs mt-2 text-danger">{{ firstError }}</p>
        </template>
    </default-field>
</template>

<script>
    import ImageLoader from './ImageLoader'
    import DeleteButton from './DeleteButton'
    import { FormField, HandlesValidationErrors, Errors } from 'laravel-nova'
    import Vapor from 'laravel-vapor'

    export default {
        props: ['resourceId', 'relatedResourceName', 'relatedResourceId', 'viaRelationship'],
        mixins: [HandlesValidationErrors, FormField],
        components: { DeleteButton, ImageLoader },
        data: () => ({
            file: null,
            fileName: '',
            fileUuid: '',
            fileKey: '',
            fileBucket: '',
            fileType: '',
            removeModalOpen: false,
            missing: false,
            deleted: false,
            uploadProgress: null,
            uploadErrors: new Errors(),
        }),
        mounted() {
            this.field.fill = formData => {
                if (this.fileUuid) {
                    formData.append(this.field.attribute, this.fileKey)
                    formData.append(this.field.attribute+'.uuid', this.fileUuid)
                    formData.append(this.field.attribute+'.name', this.fileName)
                    formData.append(this.field.attribute+'.key', this.fileKey)
                    formData.append(this.field.attribute+'.bucket', this.fileBucket)
                    formData.append(this.field.attribute+'.type', this.fileType)
                }
            }
        },
        methods: {
            /**
             * Respond to the file change
             */
            fileChange(event) {
                this.file = this.$refs.fileField.files[0]

                if(this.file)
                {
                    this.fileName = this.file.name
                    this.fileType = this.file.type

                    try {
                        Vapor.store(this.file, {
                            progress: progress => {
                                this.uploadProgress = Math.round(progress * 100);
                            }
                        }).then(response => {
                            console.log('pushed');
                            this.fileData.uuid = response.uuid
                            this.fileData.key = response.key
                            this.fileData.bucket = response.bucket
                        })
                    }
                    catch(err) {
                        console.log('============')
                        console.log(err.message)
                        console.log('============')
                    }
                }


            },
            /**
             * Confirm removal of the linked file
             */
            confirmRemoval() {
                this.removeModalOpen = true
            },
            /**
             * Close the upload removal modal
             */
            closeRemoveModal() {
                this.removeModalOpen = false
            },
            /**
             * Remove the linked file from storage
             */
            async removeFile() {
                this.uploadErrors = new Errors()
                const {
                    resourceName,
                    resourceId,
                    relatedResourceName,
                    relatedResourceId,
                    viaRelationship,
                } = this
                const attribute = this.field.attribute
                const uri = this.viaRelationship
                    ? `/nova-api/${resourceName}/${resourceId}/${relatedResourceName}/${relatedResourceId}/field/${attribute}?viaRelationship=${viaRelationship}`
                    : `/nova-api/${resourceName}/${resourceId}/field/${attribute}`
                try {
                    await Nova.request().delete(uri)
                    this.closeRemoveModal()
                    this.deleted = true
                    this.$emit('file-deleted')
                } catch (error) {
                    this.closeRemoveModal()
                    if (error.response.status == 422) {
                        this.uploadErrors = new Errors(error.response.data.errors)
                    }
                }
            },

        },
        computed: {
            /**
             * Determine if the field has an upload error.
             */
            hasError() {
                return this.uploadErrors.has(this.fieldAttribute)
            },
            /**
             * Return the first error for the field.
             */
            firstError() {
                if (this.hasError) {
                    return this.uploadErrors.first(this.fieldAttribute)
                }
            },
            /**
             * The current label of the file field.
             */
            currentLabel() {
                return this.fileName || this.__('no file selected')
            },
            /**
             * The ID attribute to use for the file field.
             */
            idAttr() {
                return this.labelFor
            },
            /**
             * The label attribute to use for the file field.
             */
            labelFor() {
                return `file-${this.field.attribute}`
            },
            /**
             * Determine whether the field has a value.
             */
            hasValue() {
                return (
                    Boolean(this.field.value || this.imageUrl) &&
                    !Boolean(this.deleted) &&
                    !Boolean(this.missing)
                )
            },
            /**
             * Determine whether the field should show the loader component.
             */
            shouldShowLoader() {
                return !Boolean(this.deleted) && Boolean(this.imageUrl)
            },
            /**
             * Determine whether the field should show the remove button.
             */
            shouldShowRemoveButton() {
                return Boolean(this.field.deletable)
            },
            /**
             * Return the preview or thumbnail URL for the field.
             */
            imageUrl() {
                return this.field.previewUrl || this.field.thumbnailUrl
            },
            /**
             * Determine the maximum width of the field.
             */
            maxWidth() {
                return this.field.maxWidth || 320
            },
        },
    }
</script>
