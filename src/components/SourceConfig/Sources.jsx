import { ReactSortable } from 'react-sortablejs';

export default function Sources({ list, setList, selected, setSelected }) {
  return list.length == 0 ? (
    <h4>No hay medios para configurar</h4>
  ) : (
    <ReactSortable
      className="sources"
      list={list}
      setList={setList}
      animation={150}
      handle="div"
    >
      
      {list.map((source, index) => (
        <div
          key={source.reference + index}
          className={selected === index && 'selected'}
          onClick={() => setSelected(index)}
        >
          <img
            src={`https://res.mobbex.com/images/sources/original/${source.reference}.png`}
          />
          <h4>{source.name}</h4>
          <input type="hidden" name={source.reference}></input>
        </div>
      ))}
    </ReactSortable>
  );
}
