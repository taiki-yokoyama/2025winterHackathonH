import React, { useState, useEffect } from 'react';
import { getSentHistory } from '../services/goodMoreService';

/**
 * Good&More送信履歴コンポーネント
 */
const GoodMoreSentHistory = () => {
  const [goodMores, setGoodMores] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  useEffect(() => {
    loadSentHistory();
  }, [currentPage]);

  const loadSentHistory = async () => {
    setLoading(true);
    try {
      const response = await getSentHistory(currentPage);
      if (response.success) {
        setGoodMores(response.data.data);
        setTotalPages(response.data.last_page);
      }
    } catch (error) {
      console.error('送信履歴の取得に失敗しました:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getStatusLabel = (status) => {
    const statusMap = {
      sent: '送信済み',
      read: '既読',
      reacted: 'リアクション済み',
    };
    return statusMap[status] || status;
  };

  if (loading) {
    return <div className="loading">読み込み中...</div>;
  }

  return (
    <div className="good-more-sent-history">
      <h2>Good&More 送信履歴</h2>
      
      {goodMores.length === 0 ? (
        <p className="empty-message">送信履歴がありません</p>
      ) : (
        <div className="history-list">
          {goodMores.map((goodMore) => (
            <div key={goodMore.id} className="history-item">
              <div className="history-header">
                <span className="receiver-name">
                  宛先: {goodMore.receiver.name}
                </span>
                <span className={`status status-${goodMore.status}`}>
                  {getStatusLabel(goodMore.status)}
                </span>
              </div>
              
              <div className="history-content">
                <div className="good-section">
                  <h4>Good</h4>
                  <p>{goodMore.good_message}</p>
                </div>
                
                <div className="more-section">
                  <h4>More</h4>
                  <p>{goodMore.more_message}</p>
                </div>
              </div>
              
              <div className="history-footer">
                <span className="date">{formatDate(goodMore.created_at)}</span>
                
                {goodMore.reactions && goodMore.reactions.length > 0 && (
                  <div className="reactions">
                    <span className="reaction-count">
                      リアクション: {goodMore.reactions.length}件
                    </span>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {totalPages > 1 && (
        <div className="pagination">
          <button
            onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
            disabled={currentPage === 1}
          >
            前へ
          </button>
          
          <span className="page-info">
            {currentPage} / {totalPages}
          </span>
          
          <button
            onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
            disabled={currentPage === totalPages}
          >
            次へ
          </button>
        </div>
      )}
    </div>
  );
};

export default GoodMoreSentHistory;
