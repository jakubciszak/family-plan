import apiClient from './apiClient';

const personalisationService = {
    getMine() {
        return apiClient.get('/api/personalisation');
    },

    getOf(userId) {
        return apiClient.get(`/api/personalisation/of/${userId}`);
    },

    save(changes) {
        return apiClient.put('/api/personalisation', changes);
    },

    getPictures() {
        return apiClient.get('/api/personalisation/pictures');
    },

    keepPicture(purpose, data) {
        return apiClient.post('/api/personalisation/pictures', { purpose, data });
    },

    forgetPicture(id) {
        return apiClient.delete(`/api/personalisation/pictures/${id}`);
    },
};

export default personalisationService;
