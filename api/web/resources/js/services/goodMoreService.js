/**
 * Good&More API Service
 */

const API_BASE_URL = '/api';

/**
 * Good&Moreを送信
 */
export const sendGoodMore = async (receiverId, goodMessage, moreMessage) => {
  const response = await fetch(`${API_BASE_URL}/good-more/send`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${getAuthToken()}`,
    },
    body: JSON.stringify({
      receiver_id: receiverId,
      good_message: goodMessage,
      more_message: moreMessage,
    }),
  });

  return await response.json();
};

/**
 * 送信履歴を取得
 */
export const getSentHistory = async (page = 1, perPage = 20) => {
  const response = await fetch(
    `${API_BASE_URL}/good-more/sent?page=${page}&per_page=${perPage}`,
    {
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`,
      },
    }
  );

  return await response.json();
};

/**
 * 受信履歴を取得
 */
export const getReceivedHistory = async (page = 1, perPage = 20) => {
  const response = await fetch(
    `${API_BASE_URL}/good-more/received?page=${page}&per_page=${perPage}`,
    {
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`,
      },
    }
  );

  return await response.json();
};

/**
 * Good&More詳細を取得
 */
export const getGoodMoreDetail = async (id) => {
  const response = await fetch(`${API_BASE_URL}/good-more/${id}`, {
    headers: {
      'Authorization': `Bearer ${getAuthToken()}`,
    },
  });

  return await response.json();
};

/**
 * リアクションを追加
 */
export const addReaction = async (id, reactionType, reactionContent = null) => {
  const response = await fetch(`${API_BASE_URL}/good-more/${id}/reaction`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${getAuthToken()}`,
    },
    body: JSON.stringify({
      reaction_type: reactionType,
      reaction_content: reactionContent,
    }),
  });

  return await response.json();
};

/**
 * リアクションを削除
 */
export const removeReaction = async (id) => {
  const response = await fetch(`${API_BASE_URL}/good-more/${id}/reaction`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${getAuthToken()}`,
    },
  });

  return await response.json();
};

/**
 * 認証トークンを取得（実装に応じて変更）
 */
const getAuthToken = () => {
  return localStorage.getItem('auth_token') || '';
};
