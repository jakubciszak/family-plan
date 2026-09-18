import { BASE_URL } from './base-url';
import apiClient from './client';
import { accessTokenInHand } from './tokenStore';

export type Purpose = 'avatar' | 'backdrop';

export type KeptPicture = {
  id: string;
  purpose: Purpose;
};

export const pictureSource = (id: string) => {
  const token = accessTokenInHand();

  return {
    uri: `${BASE_URL}/api/personalisation/pictures/${id}`,
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  };
};

export const keepPicture = (purpose: Purpose, data: string): Promise<KeptPicture> =>
  apiClient.post<KeptPicture>('/api/personalisation/pictures', { purpose, data });

export const listPictures = async (): Promise<KeptPicture[]> => {
  const { pictures } = await apiClient.get<{ pictures: KeptPicture[] }>(
    '/api/personalisation/pictures'
  );

  return pictures;
};

export const forgetPicture = (id: string): Promise<unknown> =>
  apiClient.delete(`/api/personalisation/pictures/${id}`);
